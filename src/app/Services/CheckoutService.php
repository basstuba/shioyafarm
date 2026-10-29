<?php

namespace App\Services;

use App\Enums\BankTransferCancelResult;
use App\Enums\CheckoutResult;
use App\Enums\OrderTokenStatus;
use App\Mail\ThanksMail;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * 注文の確定（決済ページへの遷移・銀行振込・保存）の共通処理
 *
 * 購入（PurchaseService）と予約（ReservationService）が共通で使う。
 * 注文の再計算と保存の中身だけを各サービスが持ち、二重送信の対策（注文トークン・会員ごとの排他ロック・保存済みの記録）、
 * Stripe Checkout・銀行振込（PaymentIntent）の進め方、前の注文の片づけはここにまとめる。
 * 保存待ちの注文・注文トークンは、サービスごとに別のストア（セッションのキー・キャッシュのキー）を使い、互いに干渉しない。
 * サンクスメールは、どの支払方法・経路でも、保存した直後にここ（completeWithLock）から1通だけ送る。
 *
 * @template TSaved of Model 保存する購入履歴（Purchase_history）・予約（Reservation）
 */
abstract class CheckoutService
{
    /** 注文トークンが使用済み・不正・期限切れのときのエラーメッセージ（ページを開き直したときに、新しいトークンになる） */
    public const string STALE_MESSAGE = 'この画面は古くなっています。もう一度内容を確認してください';

    public function __construct(
        protected readonly PaymentGatewayInterface $paymentGateway,
        protected readonly PendingOrderStore $pendingOrderStore,
        protected readonly UserRepositoryInterface $userRepository,
        protected readonly OrderTokenStore $orderTokenStore,
        protected readonly DeliveryAddressService $deliveryAddressService,
    ) {}

    /**
     * 排他ロック・保存済みの記録のキーの接頭辞（購入と予約で分ける）
     */
    abstract protected function lockNamespace(): string;

    /**
     * サンクスページのルート名
     */
    abstract protected function thanksRoute(): string;

    /**
     * Stripe Checkout の success_url にするルート名（カード専用）
     */
    abstract protected function successRoute(): string;

    /**
     * 振込先のご案内ページのルート名
     */
    abstract protected function bankTransferRoute(): string;

    /**
     * Stripe Checkout の「戻る」（cancel_url）で戻るURL（入力を復元する合図の restore を付ける）
     */
    abstract protected function cancelUrl(PendingOrder $order): string;

    /**
     * 注文トークンが使えないとき（使用済み・不正）に戻すページのURL
     *
     * @param  array<string, mixed>  $data  リクエストの検証済みの値
     */
    abstract protected function staleRedirectUrl(array $data): string;

    /**
     * 別のリクエストが処理中のときのエラーメッセージ（注文は作られていないので、もう一度押してよい）
     */
    abstract protected function busyMessage(): string;

    /**
     * 支払い済みの銀行振込を保存できなかったときのエラーメッセージ（押し直しは促さない。保存待ちの注文は残してあり、ページを開き直したときに保存する）
     */
    abstract protected function paidPendingMessage(): string;

    /**
     * 検証済みの入力から、金額を再計算した保存待ちの注文を作る
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    abstract protected function createPendingOrder(User $user, array $data): PendingOrder;

    /**
     * お届け先住所を求め、その都道府県で金額の内訳を計算する（createPendingOrder の中で使う）
     *
     * @param  Closure(string): OrderSummary  $summarize  都道府県から金額の内訳を計算する
     * @return array{array{prefecture: string, city: string}, OrderSummary}
     *
     * @throws ValidationException 住所が正しくないとき・都道府県が送料の地域に対応していないとき
     */
    protected function summarizeForAddress(User $user, string $addressId, Closure $summarize): array
    {
        $address = $this->deliveryAddressService->resolve($user, $addressId);
        if ($address === null) {
            throw ValidationException::withMessages(['address_id' => 'お届け先住所が正しくありません']);
        }

        try {
            $summary = $summarize($address['prefecture']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['address_id' => 'お届け先住所の都道府県が正しくありません']);
        }

        return [$address, $summary];
    }

    /**
     * 保存待ちの注文を保存する（銀行振込のときは振込先も）
     *
     * @return TSaved 保存した購入履歴・予約（サンクスメールの元になる）
     *
     * @throws Throwable 保存に失敗したとき
     */
    abstract protected function save(PendingOrder $order): Model;

    /**
     * 保存した購入履歴・予約から、サンクスメールを作る
     *
     * @param  TSaved  $saved
     */
    abstract protected function thanksMail(Model $saved): ThanksMail;

    /**
     * 保存待ちの注文を取得する（その会員の注文だけ）
     */
    public function getPendingOrder(User $user): ?PendingOrder
    {
        $order = $this->pendingOrderStore->get();

        return $order !== null && $order->userId === $user->id ? $order : null;
    }

    /**
     * 注文ページに入れる注文トークンを発行する
     *
     * 注文ページを開くたびに新しく発行する。ただし、検証エラーなどで戻ってきたとき（フォームの入力を復元するとき）は、
     * 前のトークンがまだ使われていなければそのまま使い回す。
     *
     * @param  string|null  $previous  前の画面で送られたトークン（検証エラーで戻ったときの入力）
     */
    public function issueOrderToken(User $user, ?string $previous = null): string
    {
        if ($previous !== null && $this->orderTokenStore->status($user->id, $previous) === OrderTokenStatus::Issued) {
            return $previous;
        }

        return $this->orderTokenStore->issue($user->id);
    }

    /**
     * 「注文を確定する」の処理：注文を再計算し、移動先を返す
     *
     * - 代金引換：Stripe を通さず、ここで注文データを保存して、サンクスページへ移動する
     * - クレジットカード：保存待ちにして、Stripe Checkout の決済ページへ移動する（注文データは決済完了ページで保存する）
     * - 銀行振込：Stripe に PaymentIntent を作って振込先を取得し、保存待ちにして「振込先のご案内」ページへ移動する。
     *   作った時点で Stripe の顧客残高から支払い済みになっていたときは、ここで注文データを保存して、サンクスページへ移動する。
     *
     * 会員ごとの排他ロックの中で処理する（ボタンの連打・複数タブでの同時送信で、決済ページや PaymentIntent を二重に作らない）。
     * ロックは数秒（purchase.begin_lock_wait）待ち、取れなければ「処理中」のエラーにする。
     * ロックの中で、注文ページが発行した1回限りの注文トークンを使用済みにする。使用済み・不正・期限切れのトークンでは注文を作らない
     * （直前の送信で注文データを保存済みなら、サンクスページへ移動する。そうでなければ注文ページへ戻して、画面が古いことを案内する）。
     * 前の保存待ちの注文を上書きする前に、前の注文の決済を片づける（前の Checkout Session の失効、前の銀行振込のキャンセル。
     * すでに支払い済みだった場合は、その注文の注文データを先に保存する）。
     *
     * @param  array<string, mixed>  $data  PurchaseRequest の検証済みの値
     *
     * @throws ValidationException 住所・商品が正しくないとき、注文トークンが使えないとき、前の注文の決済を片づけられなかったとき、別のリクエストが処理中のとき
     * @throws PaymentGatewayException Stripe の Checkout Session・PaymentIntent を作れなかったとき
     */
    public function beginCheckout(User $user, array $data): CheckoutDestination
    {
        $lock = Cache::lock($this->lockNamespace().'-begin:'.$user->id, (int) config('purchase.begin_lock_ttl'));
        try {
            $lock->block((int) config('purchase.begin_lock_wait'));
        } catch (LockTimeoutException) {
            Log::warning('注文確定処理のロックを取得できませんでした', ['user_id' => $user->id]);

            throw ValidationException::withMessages(['payment' => $this->busyMessage()]);
        }

        try {
            return $this->beginCheckoutLocked($user, $data);
        } finally {
            $lock->release();
        }
    }

    /**
     * beginCheckout の本体（会員ごとのロックを取った状態で呼ぶ）
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    private function beginCheckoutLocked(User $user, array $data): CheckoutDestination
    {
        /** @var array{order_token: string} $data */
        $token = $data['order_token'];
        $status = $this->orderTokenStore->status($user->id, $token);

        if ($status === OrderTokenStatus::Completed) {
            // 直前の送信で保存済み（同じ画面からの再送信）。注文は作らず、サンクスページへ
            return new CheckoutDestination(route($this->thanksRoute()), true);
        }

        if ($status !== OrderTokenStatus::Issued) {
            Log::warning('使用済み・不正な注文トークンで注文が送信されました', ['user_id' => $user->id]);

            throw ValidationException::withMessages(['order_token' => self::STALE_MESSAGE])
                ->redirectTo($this->staleRedirectUrl($data));
        }

        // 住所・商品の検証に通らなかったときはトークンを使わない（注文ページで直して、そのまま送り直せる）
        /** @var array<string, mixed> $data */
        $order = $this->createPendingOrder($user, $data);
        $this->orderTokenStore->markUsed($user->id, $token);

        $destination = $this->proceedCheckout($user, $order);

        if ($destination->completed) {
            $this->orderTokenStore->markCompleted($user->id, $token);
        }

        return $destination;
    }

    /**
     * 前の注文の決済を片づけてから、支払方法ごとに注文を進める
     *
     * @throws ValidationException
     * @throws PaymentGatewayException
     */
    private function proceedCheckout(User $user, PendingOrder $order): CheckoutDestination
    {
        $this->retirePreviousOrder($user);

        if (! $order->paymentMethod->usesBankTransfer() && ! $order->paymentMethod->usesCheckout()) {
            return $this->saveNow($user, $order, false);
        }

        $customerId = $this->ensureStripeCustomer($user);

        if ($order->paymentMethod->usesBankTransfer()) {
            return $this->beginBankTransfer($user, $customerId, $order);
        }

        $session = $this->paymentGateway->createCheckoutSession(
            $order->paymentMethod,
            $customerId,
            $this->toStripeLineItems($order),
            route($this->successRoute()).'?session_id={CHECKOUT_SESSION_ID}',
            // 「戻る」で戻ったことを注文ページに伝える（restore を見て、フラッシュに置き換えて入力を1回だけ復元する）
            $this->cancelUrl($order),
        );

        if ($session->url === null) {
            throw new PaymentGatewayException('Checkout Session のURLが取得できませんでした');
        }

        $this->pendingOrderStore->put($order->withCheckoutSessionId($session->id));

        return new CheckoutDestination($session->url);
    }

    /**
     * 保存待ちの注文を、Stripe への確認なしでその場で保存する（代金引換、作った時点で支払い済みだった銀行振込）
     *
     * 保存できなかったとき（別のリクエストが処理中・例外）の扱いは、支払い済みかどうかで分ける。
     * - 支払い済みでない（代金引換）：保存待ちの注文を必ず消す（例外のときも）。まだ何も請求していないため、残すと古い注文が
     *   注文ページに復元されたり、次の注文の片づけの対象になったりする。押し直してよいと案内する。
     * - 支払い済み（銀行振込）：保存待ちの注文を残す。注文ページを開いたときに保存する（settlePaidBankTransfer）。
     *   押し直しは促さない（新しい注文が重複して作られるため）。
     *
     * @param  bool  $alreadyPaid  Stripe の顧客残高から支払い済みか
     *
     * @throws ValidationException 保存できなかったとき
     */
    private function saveNow(User $user, PendingOrder $order, bool $alreadyPaid): CheckoutDestination
    {
        $this->pendingOrderStore->put($order);

        $saved = false;
        try {
            $saved = $this->completeWithLock($user, fn (): ?CheckoutResult => null)->isSaved();
        } finally {
            if (! $saved && ! $alreadyPaid) {
                $this->pendingOrderStore->forget();
            }
        }

        if (! $saved) {
            throw ValidationException::withMessages(['payment' => $alreadyPaid ? $this->paidPendingMessage() : $this->busyMessage()]);
        }

        return new CheckoutDestination(route($this->thanksRoute()), true);
    }

    /**
     * 作った時点で支払い済みだった銀行振込の注文が、保存待ちのまま残っていれば、保存する
     *
     * 保存のロックを取れなかった注文（saveNow）を、注文ページを開いたときに保存する。
     * 振込先の案内を待つ注文ではなく、支払い済み（PaymentIntent があり、振込先がない）の注文だけが対象。
     * 保存前に Stripe から PaymentIntent を取得し直して、状態と金額を確認する。
     *
     * @return CheckoutResult|null 対象の保存待ちの注文がなければ null
     */
    public function settlePaidBankTransfer(User $user): ?CheckoutResult
    {
        $order = $this->getPendingOrder($user);
        if ($order === null || ! $order->isPaidBankTransfer()) {
            return null;
        }

        return $this->confirmBankTransfer($user);
    }

    /**
     * 銀行振込の「振込先のご案内」を表示できる保存待ちの注文を取得する
     *
     * 銀行振込で、PaymentIntent と振込先がある注文だけを返す。
     */
    public function getBankTransferOrder(User $user): ?PendingOrder
    {
        $order = $this->getPendingOrder($user);

        return $order !== null && $order->paymentMethod->usesBankTransfer() && $order->paymentIntentId !== null && $order->bankTransfer !== null
            ? $order
            : null;
    }

    /**
     * 「振込先のご案内」の「戻る」：PaymentIntent をキャンセルして、注文データは作らない
     *
     * 入力内容を注文ページに残すため、保存待ちの注文は振込先の情報だけ外して残す。
     * キャンセルに失敗したときは PaymentIntent を取得し直し、すでに入金済み・入金処理中なら Paid を返す
     * （呼び出し側で注文データを保存する）。それ以外の失敗は Failed を返し、保存待ちの注文は変えない。
     *
     * @return BankTransferCancelResult|null 保存待ちの注文がなければ null
     */
    public function cancelBankTransfer(User $user): ?BankTransferCancelResult
    {
        if ($this->getPendingOrder($user) === null) {
            return null;
        }

        return $this->cancelPendingBankTransfer($user);
    }

    /**
     * 購入完了の処理（クレジットカード）：決済を確認して注文データを保存する
     *
     * 保存待ちの注文が無ければ何もしない（再読み込みで二重に保存しない）。
     * - クレジットカード：セッションIDの一致と、Checkout Session が支払い済み（complete かつ paid）であることを確認してから保存する。
     * - 銀行振込：ここでは保存しない（案内ページの「振込み」＝ confirmBankTransfer でだけ保存する）。
     * - 代金引換：ここでは保存しない（「注文を確定する」＝ beginCheckout で保存する。保存待ちに残っていた古い注文は NotPaid として扱う）。
     * 会員ごとの排他ロックの中で、保存待ちの注文を確認し直して保存するため、同時に届いたリクエストでも二重に保存しない。
     * 保存したら保存待ちの注文を消し、カートから購入した場合は購入した数量だけカートから減らす。
     */
    public function completeCheckout(User $user, ?string $sessionId): CheckoutResult
    {
        return $this->completeWithLock($user, function (PendingOrder $order) use ($sessionId): ?CheckoutResult {
            if ($order->paymentMethod->usesBankTransfer()) {
                return CheckoutResult::UseBankTransferConfirmation;
            }

            return $order->paymentMethod->usesCheckout() ? $this->verifyCheckout($order, $sessionId) : CheckoutResult::NotPaid;
        });
    }

    /**
     * 銀行振込の「振込み」：PaymentIntent の状態と金額を確認して注文データを保存する
     *
     * PaymentIntent が振込先の案内中（requires_action で振込先あり）・入金処理中（processing）・入金済み（succeeded）で、
     * 金額が注文の合計と一致していることを Stripe から取得して確認する。排他・二重保存の防止は completeCheckout と同じ。
     */
    public function confirmBankTransfer(User $user): CheckoutResult
    {
        return $this->completeWithLock($user, function (PendingOrder $order): ?CheckoutResult {
            if (! $order->paymentMethod->usesBankTransfer()) {
                return CheckoutResult::NotPaid;
            }

            return $this->verifyBankTransfer($order);
        });
    }

    /**
     * 会員ごとの排他ロックの中で、検証して注文データを保存する
     *
     * ロックを取ったあとに、同じ注文がすでに保存されていないかを記録（キャッシュ）で確認する
     * （セッションは同時リクエストの間で共有されないため、保存待ちの注文だけでは二重保存を防げない）。
     * 銀行振込の振込先の unique 違反（同じ PaymentIntent の二重保存）も、保存済みとして扱う。
     * この呼び出しで新しく保存したときだけ、サンクスメールの送信を登録する（送るのはレスポンスの後。保存済みだった場合は送らない）。
     *
     * @param  Closure(PendingOrder): ?CheckoutResult  $verify  検証。保存してはいけないときは結果を返し、よければ null を返す
     */
    private function completeWithLock(User $user, Closure $verify): CheckoutResult
    {
        $order = $this->getPendingOrder($user);
        if ($order === null) {
            return CheckoutResult::NoPendingOrder;
        }

        $lock = Cache::lock($this->lockNamespace().'-complete:'.$user->id, (int) config('purchase.complete_lock_ttl'));
        try {
            $lock->block((int) config('purchase.complete_lock_wait'));
        } catch (LockTimeoutException) {
            Log::warning('購入完了処理のロックを取得できませんでした', ['user_id' => $user->id]);

            return CheckoutResult::Busy;
        }

        try {
            $completedKey = $this->lockNamespace().'-completed:'.$order->orderId;

            if (Cache::has($completedKey)) {
                $this->finish($order);

                return CheckoutResult::AlreadyCompleted;
            }

            $failure = $verify($order);
            if ($failure !== null) {
                return $failure;
            }

            try {
                $saved = $this->save($order);
            } catch (UniqueConstraintViolationException $e) {
                Log::warning('購入データはすでに保存されています', ['message' => $e->getMessage()]);
                Cache::put($completedKey, true, now()->addDays((int) config('purchase.completed_marker_days')));
                $this->finish($order);

                return CheckoutResult::AlreadyCompleted;
            }

            // 保存できた直後に、サンクスメールの送信を登録する（送信自体はレスポンスを返した後）。
            // このあとの完了マーカーの記録や片づけ（finish）で例外が起きても、登録済みなのでメールは送られる
            $this->sendThanksMail($saved, $user);

            Cache::put($completedKey, true, now()->addDays((int) config('purchase.completed_marker_days')));
            $this->finish($order);

            return CheckoutResult::Completed;
        } finally {
            $lock->release();
        }
    }

    /**
     * 保存した購入履歴・予約のサンクスメールを、会員登録のメールアドレスへ送る（送信はレスポンスを返した後）
     *
     * ここでは送信を defer に登録するだけで、実際に送るのはリクエストの終了時。排他ロックの中から呼んでも、送信はロックの外になり、
     * サンクスページの表示もメールサーバーの応答を待たない。登録後に処理が例外で終わっても（レスポンスが500でも）、always で送る。
     * 宛先はメール（ThanksMail）が持つ。キューは使わず、同じプロセスの中で送る。送信に失敗しても例外は外に出さない
     * （購入・予約は保存済み）。失敗はログに残す。
     * 会員は呼び出し側が持っているものを渡し、メールの組み立てで会員を読み込み直さない。
     *
     * @param  TSaved  $saved
     */
    private function sendThanksMail(Model $saved, User $user): void
    {
        $saved->setRelation('user', $user);

        defer(function () use ($saved): void {
            try {
                Mail::send($this->thanksMail($saved));
            } catch (Throwable $e) {
                Log::error('サンクスメールの送信に失敗しました', [
                    'saved' => $saved::class,
                    'id' => $saved->getKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }, always: true);
    }

    /**
     * 保存後の片づけ：保存待ちの注文を消す
     *
     * 購入はこれに加えて、カートから購入した数量だけカートから減らす（PurchaseService で上書きする）。
     */
    protected function finish(PendingOrder $order): void
    {
        $this->pendingOrderStore->forget();
    }

    /**
     * カードの Checkout Session を確認する
     *
     * @return CheckoutResult|null 保存してよければ null
     */
    private function verifyCheckout(PendingOrder $order, ?string $sessionId): ?CheckoutResult
    {
        if ($order->checkoutSessionId === null || $sessionId === null || ! hash_equals($order->checkoutSessionId, $sessionId)) {
            return CheckoutResult::SessionMismatch;
        }

        try {
            $session = $this->paymentGateway->retrieveCheckoutSession($sessionId);
        } catch (PaymentGatewayException $e) {
            Log::error('Checkout Session の確認に失敗しました', ['message' => $e->getMessage()]);

            return CheckoutResult::VerificationFailed;
        }

        return $session->isComplete() ? null : CheckoutResult::NotPaid;
    }

    /**
     * 銀行振込の PaymentIntent を確認する
     *
     * @return CheckoutResult|null 保存してよければ null
     */
    private function verifyBankTransfer(PendingOrder $order): ?CheckoutResult
    {
        if ($order->paymentIntentId === null) {
            return CheckoutResult::NotPaid;
        }

        try {
            $intent = $this->paymentGateway->retrieveBankTransferIntent($order->paymentIntentId);
        } catch (PaymentGatewayException $e) {
            Log::error('銀行振込の PaymentIntent の確認に失敗しました', ['message' => $e->getMessage()]);

            return CheckoutResult::VerificationFailed;
        }

        $statusOk = ($intent->status === 'requires_action' && $intent->instructions !== null)
            || in_array($intent->status, ['processing', 'succeeded'], true);

        return $statusOk && $intent->id === $order->paymentIntentId && $intent->amount === $order->totalPrice
            ? null
            : CheckoutResult::NotPaid;
    }

    /**
     * 会員の Stripe Customer を用意する（会員ごとに使い回す）
     *
     * 保存済みの Customer ID を再利用し、無い・Stripe 側で削除されていたときは作り直して保存する。
     *
     * @throws PaymentGatewayException
     */
    private function ensureStripeCustomer(User $user): string
    {
        $customerId = $this->paymentGateway->ensureCustomer($user->email, $user->stripe_customer_id);

        if ($customerId !== $user->stripe_customer_id) {
            $this->userRepository->updateStripeCustomerId($user, $customerId);
        }

        return $customerId;
    }

    /**
     * 銀行振込の PaymentIntent を作る
     *
     * - 振込待ち（requires_action で振込先あり）：振込先を保存待ちの注文に入れ、「振込先のご案内」ページへ移動する。
     * - 作った時点で顧客残高から支払い済み（succeeded・processing）：金額が合っていれば、振込先なしで注文データを保存し、
     *   サンクスページへ移動する（残高が使われたのに注文が残らない状態を避ける）。保存できなかったときは保存待ちの注文を残す（saveNow）。
     * - それ以外：作った PaymentIntent を取り消してエラーにする。
     *
     * @throws PaymentGatewayException PaymentIntent を作れなかった・振込先を取得できなかったとき
     * @throws ValidationException 支払い済みだったが、別のリクエストが処理中で保存できなかったとき（押し直しは促さない）
     */
    private function beginBankTransfer(User $user, string $customerId, PendingOrder $order): CheckoutDestination
    {
        $intent = $this->paymentGateway->createBankTransferIntent($customerId, $order->totalPrice);

        if (in_array($intent->status, ['succeeded', 'processing'], true) && $intent->id !== '' && $intent->amount === $order->totalPrice) {
            return $this->saveNow($user, $order->withPaymentIntentId($intent->id), true);
        }

        if ($intent->status !== 'requires_action' || $intent->instructions === null || $intent->amount !== $order->totalPrice) {
            // 想定外の状態のまま残さないよう、作った PaymentIntent は取り消す
            try {
                $this->paymentGateway->cancelPaymentIntent($intent->id);
            } catch (PaymentGatewayException $e) {
                Log::error('銀行振込の PaymentIntent のキャンセルに失敗しました', ['message' => $e->getMessage()]);
            }

            throw new PaymentGatewayException('銀行振込の振込先を取得できませんでした（status: '.$intent->status.'）');
        }

        $this->pendingOrderStore->put($order->withBankTransfer($intent->id, $intent->instructions));

        return new CheckoutDestination(route($this->bankTransferRoute()));
    }

    /**
     * 新しい注文で保存待ちの注文を上書きする前に、前の注文の決済を片づける
     *
     * - 前の銀行振込：PaymentIntent をキャンセルする。キャンセルに失敗して入金済み・入金処理中だったときは、その注文の注文データを保存する。
     * - 前のカード：Checkout Session を失効させる。すでに支払い済みだったとき（失効に失敗して確認し直したときを含む）は、
     *   その注文の注文データを保存する（支払いを受けた注文を失わないことを優先する）。
     *
     * @throws ValidationException 前の注文を片づけられなかったとき（新しい注文は進めない）
     */
    private function retirePreviousOrder(User $user): void
    {
        $previous = $this->getPendingOrder($user);
        if ($previous === null) {
            return;
        }

        if ($previous->paymentIntentId !== null) {
            $result = $this->cancelPendingBankTransfer($user);
            if ($result === BankTransferCancelResult::Failed) {
                throw ValidationException::withMessages(['payment' => '前回の銀行振込のお手続きを取り消せませんでした。しばらく時間をおいてから、もう一度お試しください']);
            }
            if ($result === BankTransferCancelResult::Paid) {
                $this->settlePreviousOrder($this->confirmBankTransfer($user));
            }

            return;
        }

        if ($previous->checkoutSessionId !== null) {
            $this->retireCheckoutSession($user, $previous->checkoutSessionId);
        }
    }

    /**
     * 前のカードの Checkout Session を失効させる（支払い済みなら、その注文を保存する）
     *
     * Stripe に問い合わせられないときは、支払い済みかどうかを確認できないため、新しい注文に進まない。
     *
     * @throws ValidationException 前の Checkout Session を確認できなかったとき
     */
    private function retireCheckoutSession(User $user, string $sessionId): void
    {
        try {
            $session = $this->paymentGateway->retrieveCheckoutSession($sessionId);

            if ($session->isOpen()) {
                try {
                    $session = $this->paymentGateway->expireCheckoutSession($sessionId);
                } catch (PaymentGatewayException $e) {
                    // 失効しようとした瞬間に支払われた可能性があるため、もう一度状態を確認する
                    Log::warning('Checkout Session を失効できませんでした', ['message' => $e->getMessage()]);
                    $session = $this->paymentGateway->retrieveCheckoutSession($sessionId);
                }
            }
        } catch (PaymentGatewayException $e) {
            Log::error('前の Checkout Session を確認できませんでした', ['message' => $e->getMessage()]);

            throw ValidationException::withMessages(['payment' => '前回のお支払いの確認ができませんでした。しばらく時間をおいてから、もう一度お試しください']);
        }

        if ($session->isComplete()) {
            $this->settlePreviousOrder($this->completeCheckout($user, $sessionId));
        }
    }

    /**
     * 支払い済みだった前の注文を保存した結果を確認する（保存できていなければ、新しい注文は進めない）
     *
     * @throws ValidationException
     */
    private function settlePreviousOrder(CheckoutResult $result): void
    {
        if (! $result->isSaved() && $result !== CheckoutResult::NoPendingOrder) {
            throw ValidationException::withMessages(['payment' => '前回のお支払いの確認が完了していません。しばらく時間をおいてから、もう一度お試しください']);
        }
    }

    /**
     * 保存待ちの注文に銀行振込の PaymentIntent があれば、キャンセルして振込先の情報を外す
     *
     * キャンセルに失敗したときは PaymentIntent を取得し直して判断する。
     * - すでにキャンセル済み：振込先の情報を外す（Cancelled）
     * - 入金済み・入金処理中：外さずに Paid を返す（呼び出し側で注文データを保存する）
     * - それ以外（状態も確認できない）：外さずに Failed を返す
     */
    private function cancelPendingBankTransfer(User $user): BankTransferCancelResult
    {
        $order = $this->getPendingOrder($user);
        if ($order === null || $order->paymentIntentId === null) {
            return BankTransferCancelResult::Cancelled;
        }

        try {
            $this->paymentGateway->cancelPaymentIntent($order->paymentIntentId);
        } catch (PaymentGatewayException $e) {
            Log::error('銀行振込の PaymentIntent のキャンセルに失敗しました', ['message' => $e->getMessage()]);

            try {
                $status = $this->paymentGateway->retrieveBankTransferIntent($order->paymentIntentId)->status;
            } catch (PaymentGatewayException $retrieveError) {
                Log::error('銀行振込の PaymentIntent の状態を確認できませんでした', ['message' => $retrieveError->getMessage()]);

                return BankTransferCancelResult::Failed;
            }

            if (in_array($status, ['succeeded', 'processing'], true)) {
                return BankTransferCancelResult::Paid;
            }
            if ($status !== 'canceled') {
                return BankTransferCancelResult::Failed;
            }
        }

        $this->pendingOrderStore->put($order->withoutBankTransfer());

        return BankTransferCancelResult::Cancelled;
    }

    /**
     * Stripe に渡す行（商品ごとの行 + 送料の行）にする
     *
     * @return list<array{name: string, unit_amount: int, quantity: int}>
     */
    private function toStripeLineItems(PendingOrder $order): array
    {
        $lineItems = array_map(fn (array $item): array => [
            'name' => $item['name'],
            'unit_amount' => $item['price'],
            'quantity' => $item['quantity'],
        ], $order->items);

        if ($order->shippingFee > 0) {
            $lineItems[] = ['name' => '送料', 'unit_amount' => $order->shippingFee, 'quantity' => 1];
        }

        return $lineItems;
    }

    /**
     * 銀行振込の注文のときだけ、振込先のテーブル（purchase_bank_transfers / reservation_bank_transfers）に保存する行を作る（案内ページに表示した振込先・金額と同じ値）
     *
     * 口座種別は Stripe の値（futsu / toza）のまま保存する。
     * PaymentIntent を作った時点で支払い済みだった注文は、振込先の案内が無いため、振込先の各項目を null にして PaymentIntent ID と合計金額だけ保存する。
     *
     * @return array{stripe_payment_intent_id: string, bank_name: string|null, branch_name: string|null, account_type: string|null, account_number: string|null, account_holder_name: string|null, amount: int}|null
     */
    protected function bankTransferRow(PendingOrder $order): ?array
    {
        if (! $order->paymentMethod->usesBankTransfer() || $order->paymentIntentId === null) {
            return null;
        }

        return [
            'stripe_payment_intent_id' => $order->paymentIntentId,
            'bank_name' => $order->bankTransfer?->bankName,
            'branch_name' => $order->bankTransfer?->branchName,
            'account_type' => $order->bankTransfer?->accountType,
            'account_number' => $order->bankTransfer?->accountNumber,
            'account_holder_name' => $order->bankTransfer?->accountHolderName,
            'amount' => $order->bankTransfer->amount ?? $order->totalPrice,
        ];
    }
}
