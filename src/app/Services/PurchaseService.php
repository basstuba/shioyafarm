<?php

namespace App\Services;

use App\Enums\BankTransferCancelResult;
use App\Enums\CheckoutResult;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Item;
use App\Models\User;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * 購入サービス
 *
 * 購入ページの金額計算、「注文を確定する」（決済ページへの遷移）、購入完了時の保存を担当する。
 * 金額はフォームの値を信用せず、DBの価格と config/shipping.php から再計算する。
 */
class PurchaseService
{
    /**
     * 購入ページで保存待ちの注文の入力を復元する合図（セッションのフラッシュのキー）
     *
     * 復元するのは、決済ページ（Stripe Checkout）の「戻る」や振込先のご案内の「戻る」で戻った直後の1回だけ。
     * 通常の訪問では会員登録の内容で表示する。
     */
    public const string RESTORE_FLASH_KEY = 'restore_pending_order';

    /** 銀行振込のサンクスページを「保存した直後の1回だけ」表示するための合図（セッションのフラッシュのキー） */
    public const string THANKS_FLASH_KEY = 'purchase_completed';

    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly PurchaseHistoryRepositoryInterface $purchaseHistoryRepository,
        private readonly CartService $cartService,
        private readonly ShippingService $shippingService,
        private readonly DeliveryAddressService $deliveryAddressService,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PendingOrderStore $pendingOrderStore,
    ) {}

    /**
     * 購入できる商品を取得する
     *
     * @throws ModelNotFoundException<Item> 存在しない商品・予約限定の商品のとき
     */
    public function findPurchasableItem(int $itemId): Item
    {
        $item = $this->itemRepository->findById($itemId);
        if ($item->is_reserved_only) {
            throw (new ModelNotFoundException)->setModel(Item::class, [$itemId]);
        }

        return $item;
    }

    /**
     * カートに購入できる商品が1つも無いかどうか
     */
    public function isCartEmpty(): bool
    {
        return $this->cartService->getLines()->isEmpty();
    }

    /**
     * 購入ページを開いたときの商品ごとの数量を取得する
     *
     * カートから来た場合はカートの数量、商品詳細から来た場合はその商品1個。
     *
     * @return array<int, int>
     */
    public function initialQuantities(PurchaseSource $source, ?int $itemId): array
    {
        if ($source === PurchaseSource::Item) {
            return $itemId === null ? [] : [$itemId => CartService::MIN_QUANTITY];
        }

        return $this->cartService->getLines()
            ->mapWithKeys(fn (CartLine $line): array => [$line->item->id => $line->quantity])
            ->all();
    }

    /**
     * 商品ごとの数量から、購入する行を商品ID順に作る
     *
     * 数量は1〜10に収める。存在しない商品と予約限定の商品は含めない。
     *
     * @param  array<int, int>  $quantities  商品ID => 数量
     * @return Collection<int, CartLine>
     */
    public function buildLines(array $quantities): Collection
    {
        if ($quantities === []) {
            return collect();
        }

        return $this->itemRepository->findByIds(array_keys($quantities))
            ->reject(fn (Item $item): bool => $item->is_reserved_only)
            ->map(fn (Item $item): CartLine => new CartLine(
                $item,
                max(CartService::MIN_QUANTITY, min(CartService::MAX_QUANTITY, $quantities[$item->id])),
            ))
            ->values();
    }

    /**
     * 金額の内訳（小計・送料・代引手数料・合計）を計算する
     *
     * 都道府県が null のときは送料を0円にする（送料を計算できない住所で、小計だけを表示するとき用）。
     *
     * @param  Collection<int, CartLine>  $lines
     *
     * @throws InvalidArgumentException 都道府県が送料の地域に対応していないとき
     */
    public function summarize(Collection $lines, ?string $prefecture, ?PaymentMethod $method): OrderSummary
    {
        $totalWeight = $lines->sum(fn (CartLine $line): int => $line->item->weight * $line->quantity);
        $codFee = $method === PaymentMethod::Cod ? (int) config('shipping.cod_fee') : 0;

        return new OrderSummary(
            $this->cartService->calculateSubtotal($lines),
            $totalWeight,
            $prefecture === null ? 0 : $this->shippingService->calculate($prefecture, $totalWeight),
            $codFee,
        );
    }

    /**
     * 保存待ちの注文を取得する（その会員の注文だけ）
     */
    public function getPendingOrder(User $user): ?PendingOrder
    {
        $order = $this->pendingOrderStore->get();

        return $order !== null && $order->userId === $user->id ? $order : null;
    }

    /**
     * 使われなくなった代金引換の保存待ちの注文を捨てる
     *
     * 代金引換の保存待ちの注文は、購入完了ページへ移動するまでの間だけ使う。購入ページを開き直したときに残っていたら、
     * あとから購入完了ページを開いても保存されないよう消す。
     * カード・銀行振込の注文は、決済ページ側で支払いが済む可能性があるため残す（購入完了ページで決済を確認して保存する）。
     */
    public function discardStaleCodOrder(User $user): void
    {
        $order = $this->getPendingOrder($user);
        if ($order !== null && $order->paymentMethod === PaymentMethod::Cod) {
            $this->pendingOrderStore->forget();
        }
    }

    /**
     * 「注文を確定する」の処理：注文を再計算して保存待ちにし、遷移先のURLを返す
     *
     * - 代金引換：購入完了ページのURL（Stripe を通さない）
     * - クレジットカード：Stripe Checkout の決済ページのURL
     * - 銀行振込：Stripe に PaymentIntent を作って振込先を取得し、「振込先のご案内」ページのURL
     *
     * 前の保存待ちの注文を上書きする前に、前の注文の決済を片づける（前の Checkout Session の失効、前の銀行振込のキャンセル。
     * すでに支払い済みだった場合は、その注文の購入データを先に保存する）。
     *
     * @param  array<string, mixed>  $data  PurchaseRequest の検証済みの値
     *
     * @throws ValidationException 住所・商品が正しくないとき、前の注文の決済を片づけられなかったとき
     * @throws PaymentGatewayException Stripe の Checkout Session・PaymentIntent を作れなかったとき
     */
    public function beginCheckout(User $user, array $data): string
    {
        $order = $this->createPendingOrder($user, $data);

        $this->retirePreviousOrder($user);

        if (! $order->paymentMethod->usesBankTransfer() && ! $order->paymentMethod->usesCheckout()) {
            $this->pendingOrderStore->put($order);

            return route('shop.checkout.success');
        }

        $customerId = $this->ensureStripeCustomer($user);

        if ($order->paymentMethod->usesBankTransfer()) {
            return $this->beginBankTransfer($customerId, $order);
        }

        $session = $this->paymentGateway->createCheckoutSession(
            $order->paymentMethod,
            $customerId,
            $this->toStripeLineItems($order),
            route('shop.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            // 「戻る」で戻ったことを購入ページに伝える（restore を見て、フラッシュに置き換えて入力を1回だけ復元する）
            route('shop.checkout.index', array_filter(['item' => $order->itemId(), 'restore' => 1])),
        );

        if ($session->url === null) {
            throw new PaymentGatewayException('Checkout Session のURLが取得できませんでした');
        }

        $this->pendingOrderStore->put($order->withCheckoutSessionId($session->id));

        return $session->url;
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
     * 「振込先のご案内」の「戻る」：PaymentIntent をキャンセルして、購入データは作らない
     *
     * 入力内容を購入ページに残すため、保存待ちの注文は振込先の情報だけ外して残す。
     * キャンセルに失敗したときは PaymentIntent を取得し直し、すでに入金済み・入金処理中なら Paid を返す
     * （呼び出し側で購入データを保存する）。それ以外の失敗は Failed を返し、保存待ちの注文は変えない。
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
     * 購入完了の処理（クレジットカード・代金引換）：決済を確認して購入データを保存する
     *
     * 保存待ちの注文が無ければ何もしない（再読み込みで二重に保存しない）。
     * - クレジットカード：セッションIDの一致と、Checkout Session が支払い済み（complete かつ paid）であることを確認してから保存する。
     * - 代金引換：確認なしで保存する。
     * - 銀行振込：ここでは保存しない（案内ページの「振込み」＝ confirmBankTransfer でだけ保存する）。
     * 会員ごとの排他ロックの中で、保存待ちの注文を確認し直して保存するため、同時に届いたリクエストでも二重に保存しない。
     * 保存したら保存待ちの注文を消し、カートから購入した場合はカートを空にする。
     */
    public function completeCheckout(User $user, ?string $sessionId): CheckoutResult
    {
        return $this->completeWithLock($user, function (PendingOrder $order) use ($sessionId): ?CheckoutResult {
            if ($order->paymentMethod->usesBankTransfer()) {
                return CheckoutResult::UseBankTransferConfirmation;
            }

            return $order->paymentMethod->usesCheckout() ? $this->verifyCheckout($order, $sessionId) : null;
        });
    }

    /**
     * 銀行振込の「振込み」：PaymentIntent の状態と金額を確認して購入データを保存する
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
     * 会員ごとの排他ロックの中で、検証して購入データを保存する
     *
     * ロックを取ったあとに、同じ注文がすでに保存されていないかを記録（キャッシュ）で確認する
     * （セッションは同時リクエストの間で共有されないため、保存待ちの注文だけでは二重保存を防げない）。
     * 銀行振込の振込先の unique 違反（同じ PaymentIntent の二重保存）も、保存済みとして扱う。
     *
     * @param  Closure(PendingOrder): ?CheckoutResult  $verify  検証。保存してはいけないときは結果を返し、よければ null を返す
     */
    private function completeWithLock(User $user, Closure $verify): CheckoutResult
    {
        $order = $this->getPendingOrder($user);
        if ($order === null) {
            return CheckoutResult::NoPendingOrder;
        }

        $lock = Cache::lock('purchase-complete:'.$user->id, (int) config('purchase.complete_lock_ttl'));
        try {
            $lock->block((int) config('purchase.complete_lock_wait'));
        } catch (LockTimeoutException) {
            Log::warning('購入完了処理のロックを取得できませんでした', ['user_id' => $user->id]);

            return CheckoutResult::Busy;
        }

        try {
            $completedKey = 'purchase-completed:'.$order->orderId;

            if (Cache::has($completedKey)) {
                $this->finish($order);

                return CheckoutResult::AlreadyCompleted;
            }

            $failure = $verify($order);
            if ($failure !== null) {
                return $failure;
            }

            try {
                $this->save($order);
            } catch (UniqueConstraintViolationException $e) {
                Log::warning('購入データはすでに保存されています', ['message' => $e->getMessage()]);
                Cache::put($completedKey, true, now()->addDays((int) config('purchase.completed_marker_days')));
                $this->finish($order);

                return CheckoutResult::AlreadyCompleted;
            }

            Cache::put($completedKey, true, now()->addDays((int) config('purchase.completed_marker_days')));
            $this->finish($order);

            return CheckoutResult::Completed;
        } finally {
            $lock->release();
        }
    }

    /**
     * 保存後の片づけ：保存待ちの注文を消し、カートから購入した場合はカートを空にする
     */
    private function finish(PendingOrder $order): void
    {
        $this->pendingOrderStore->forget();
        if ($order->source === PurchaseSource::Cart) {
            $this->cartService->clear();
        }
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
     * stripe_customer_id は fillable に含めないため、forceFill で保存する。
     *
     * @throws PaymentGatewayException
     */
    private function ensureStripeCustomer(User $user): string
    {
        $customerId = $this->paymentGateway->ensureCustomer($user->email, $user->stripe_customer_id);

        if ($customerId !== $user->stripe_customer_id) {
            $user->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        return $customerId;
    }

    /**
     * 銀行振込の PaymentIntent を作って振込先を保存待ちの注文に入れ、「振込先のご案内」ページのURLを返す
     *
     * @throws PaymentGatewayException PaymentIntent を作れなかった・振込先を取得できなかったとき
     */
    private function beginBankTransfer(string $customerId, PendingOrder $order): string
    {
        $intent = $this->paymentGateway->createBankTransferIntent($customerId, $order->totalPrice);

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

        return route('shop.checkout.bank-transfer');
    }

    /**
     * 新しい注文で保存待ちの注文を上書きする前に、前の注文の決済を片づける
     *
     * - 前の銀行振込：PaymentIntent をキャンセルする。キャンセルに失敗して入金済み・入金処理中だったときは、その注文の購入データを保存する。
     * - 前のカード：Checkout Session を失効させる。すでに支払い済みだったとき（失効に失敗して確認し直したときを含む）は、
     *   その注文の購入データを保存する（支払いを受けた注文を失わないことを優先する）。
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
     * Stripe に問い合わせられないときはログだけ残して進める（支払い済みかどうかを確認できないため）。
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

            return;
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
     * - 入金済み・入金処理中：外さずに Paid を返す（呼び出し側で購入データを保存する）
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
     * 検証済みの入力から、金額を再計算した保存待ちの注文を作る
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function createPendingOrder(User $user, array $data): PendingOrder
    {
        /** @var array{source: string, items: list<array{item_id: int|string, quantity: int|string}>, delivery_name: string, address_id: string, delivery_tell: string, payment_method: string} $data */
        $source = PurchaseSource::from($data['source']);
        $method = PaymentMethod::from($data['payment_method']);

        $quantities = [];
        foreach ($data['items'] as $row) {
            $quantities[(int) $row['item_id']] = (int) $row['quantity'];
        }

        $lines = $this->buildLines($quantities);
        if ($lines->count() !== count($quantities) || ($source === PurchaseSource::Item && $lines->count() !== 1)) {
            throw ValidationException::withMessages(['items' => '購入できない商品が含まれています']);
        }

        $address = $this->deliveryAddressService->resolve($user, $data['address_id']);
        if ($address === null) {
            throw ValidationException::withMessages(['address_id' => 'お届け先住所が正しくありません']);
        }

        try {
            $summary = $this->summarize($lines, $address['prefecture'], $method);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['address_id' => 'お届け先住所の都道府県が正しくありません']);
        }

        return new PendingOrder(
            $user->id,
            $source,
            $data['address_id'],
            $data['delivery_name'],
            $data['delivery_tell'],
            $address['prefecture'],
            $address['city'],
            $method,
            $lines->map(fn (CartLine $line): array => [
                'item_id' => $line->item->id,
                'name' => $line->item->name.' '.$line->item->weight.'kg',
                'quantity' => $line->quantity,
                'price' => $line->item->price,
                'subtotal' => $line->amount,
            ])->all(),
            $summary->subtotal,
            $summary->shippingFee,
            $summary->codFee,
            $summary->total,
        );
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
     * 銀行振込の注文のときだけ、purchase_bank_transfers に保存する行を作る（案内ページに表示した振込先・金額と同じ値）
     *
     * 口座種別は Stripe の値（futsu / toza）のまま保存する。
     *
     * @return array{stripe_payment_intent_id: string, bank_name: string, branch_name: string, account_type: string, account_number: string, account_holder_name: string, amount: int}|null
     */
    private function bankTransferRow(PendingOrder $order): ?array
    {
        if (! $order->paymentMethod->usesBankTransfer() || $order->paymentIntentId === null || $order->bankTransfer === null) {
            return null;
        }

        return [
            'stripe_payment_intent_id' => $order->paymentIntentId,
            'bank_name' => $order->bankTransfer->bankName,
            'branch_name' => $order->bankTransfer->branchName,
            'account_type' => $order->bankTransfer->accountType,
            'account_number' => $order->bankTransfer->accountNumber,
            'account_holder_name' => $order->bankTransfer->accountHolderName,
            'amount' => $order->bankTransfer->amount,
        ];
    }

    /**
     * 保存待ちの注文を購入履歴・購入明細（銀行振込のときは振込先も）として保存する
     *
     * @throws Throwable 保存に失敗したとき
     */
    private function save(PendingOrder $order): void
    {
        $this->purchaseHistoryRepository->createWithItems(
            [
                'user_id' => $order->userId,
                'delivery_name' => $order->deliveryName,
                'delivery_prefecture' => $order->deliveryPrefecture,
                'delivery_city' => $order->deliveryCity,
                'delivery_tell' => $order->deliveryTell,
                'payment_method' => $order->paymentMethod->value,
                'shipping_fee' => $order->shippingFee,
                'cod_fee' => $order->codFee,
                'total_price' => $order->totalPrice,
            ],
            array_map(fn (array $item): array => [
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ], $order->items),
            $this->bankTransferRow($order),
        );
    }
}
