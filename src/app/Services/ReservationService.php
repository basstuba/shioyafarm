<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Mail\ReservationThanksMail;
use App\Mail\ThanksMail;
use App\Models\Item;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Override;
use Throwable;

/**
 * 予約サービス
 *
 * 予約ページの金額計算、予約の注文の作成（予約限定の商品1つ）、予約完了時の保存を担当する。
 * 「予約を確定する」（決済ページへの遷移）・銀行振込・二重送信の対策は CheckoutService にある（購入と共通）。
 * 金額はフォームの値を信用せず、DBの価格と config/shipping.php から再計算する。
 * 予約可能数は「予約を確定する」の時点でだけ確認する（保存時には再確認しない）。
 *
 * @extends CheckoutService<Reservation>
 */
class ReservationService extends CheckoutService
{
    /**
     * 予約ページで保存待ちの予約の入力を復元する合図（セッションのフラッシュのキー）
     *
     * 復元するのは、決済ページ（Stripe Checkout）の「戻る」や振込先のご案内の「戻る」で戻った直後の1回だけ。
     */
    public const string RESTORE_FLASH_KEY = 'restore_pending_reservation';

    /** サンクスページ（銀行振込）を「保存した直後の1回だけ」表示するための合図（セッションのフラッシュのキー） */
    public const string THANKS_FLASH_KEY = 'reservation_completed';

    /** 予約受付が終了した商品の予約ページを開いたとき、商品詳細に表示する案内（セッションのフラッシュのキー） */
    public const string CLOSED_FLASH_KEY = 'reservation_closed';

    /** 予約受付が終了したときの案内 */
    public const string CLOSED_MESSAGE = 'この商品の予約受付は終了しました';

    /** 別のリクエストが処理中のときのエラーメッセージ（予約は作られていないので、もう一度押してよい） */
    private const string BUSY_MESSAGE = 'ただいまご予約を処理しています。しばらく待ってから、もう一度お試しください';

    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ReservationLimitService $limitService,
        private readonly ShippingService $shippingService,
        PaymentGatewayInterface $paymentGateway,
        PendingOrderStore $pendingOrderStore,
        UserRepositoryInterface $userRepository,
        OrderTokenStore $orderTokenStore,
        DeliveryAddressService $deliveryAddressService,
    ) {
        parent::__construct($paymentGateway, $pendingOrderStore, $userRepository, $orderTokenStore, $deliveryAddressService);
    }

    /**
     * 予約できる商品（予約限定の商品）を取得する
     *
     * @throws ModelNotFoundException<Item> 存在しない商品・予約限定でない商品のとき
     */
    public function findReservableItem(int $itemId): Item
    {
        $item = $this->itemRepository->findById($itemId);
        if (! $item->is_reserved_only) {
            throw (new ModelNotFoundException)->setModel(Item::class, [$itemId]);
        }

        return $item;
    }

    /**
     * 金額の内訳（小計・送料・合計）を計算する
     *
     * 都道府県が null のときは送料を0円にする（送料を計算できない住所で、小計だけを表示するとき用）。
     * 代引手数料はない（予約は先払い）。
     *
     * @throws InvalidArgumentException 都道府県が送料の地域に対応していないとき
     */
    public function summarize(Item $item, int $quantity, ?string $prefecture): OrderSummary
    {
        $totalWeight = $item->weight * $quantity;

        return new OrderSummary(
            $item->price * $quantity,
            $totalWeight,
            $prefecture === null ? 0 : $this->shippingService->calculate($prefecture, $totalWeight),
            0,
        );
    }

    #[Override]
    protected function lockNamespace(): string
    {
        return 'reservation';
    }

    #[Override]
    protected function thanksRoute(): string
    {
        return 'shop.reservation.thanks';
    }

    #[Override]
    protected function successRoute(): string
    {
        return 'shop.reservation.success';
    }

    #[Override]
    protected function bankTransferRoute(): string
    {
        return 'shop.reservation.bank-transfer';
    }

    #[Override]
    protected function cancelUrl(PendingOrder $order): string
    {
        return route('shop.reservation.index', ['item' => $order->itemId(), 'restore' => 1]);
    }

    #[Override]
    protected function staleRedirectUrl(array $data): string
    {
        return route('shop.reservation.index', (int) ($data['item_id'] ?? 0));
    }

    #[Override]
    protected function busyMessage(): string
    {
        return self::BUSY_MESSAGE;
    }

    #[Override]
    protected function paidPendingMessage(): string
    {
        return 'お支払いは完了しています。ただいま確定処理中のため、しばらくしてから予約ページを開き直してください';
    }

    /**
     * 検証済みの入力から、金額を再計算した保存待ちの予約を作る
     *
     * 予約可能数をここで確認する（すでに予約された数量の合計＋今回の数量が予約可能数を超えるときはエラー）。
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    #[Override]
    protected function createPendingOrder(User $user, array $data): PendingOrder
    {
        /** @var array{item_id: int|string, quantity: int|string, delivery_name: string, address_id: string, delivery_tell: string, payment_method: string} $data */
        $method = PaymentMethod::from($data['payment_method']);
        try {
            $item = $this->findReservableItem((int) $data['item_id']);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages(['item_id' => '予約できない商品です']);
        }

        $quantity = (int) $data['quantity'];
        $remaining = $this->limitService->remaining($item);
        if ($remaining !== null && $quantity > $remaining) {
            throw ValidationException::withMessages([
                'quantity' => $remaining === 0 ? self::CLOSED_MESSAGE : "予約可能数を超えています（残り{$remaining}個）",
            ]);
        }

        [$address, $summary] = $this->summarizeForAddress(
            $user,
            $data['address_id'],
            fn (string $prefecture): OrderSummary => $this->summarize($item, $quantity, $prefecture),
        );

        return new PendingOrder(
            $user->id,
            PurchaseSource::Item,
            $data['address_id'],
            $data['delivery_name'],
            $data['delivery_tell'],
            $address['prefecture'],
            $address['city'],
            $method,
            [[
                'item_id' => $item->id,
                'name' => $item->display_name,
                'quantity' => $quantity,
                'price' => $item->price,
                'subtotal' => $summary->subtotal,
            ]],
            $summary->subtotal,
            $summary->shippingFee,
            0,
            $summary->total,
        );
    }

    /**
     * 保存待ちの予約を予約データ（銀行振込のときは振込先も）として保存する
     *
     * @throws Throwable 保存に失敗したとき
     */
    #[Override]
    protected function save(PendingOrder $order): Reservation
    {
        $item = $order->items[0];

        return $this->reservationRepository->createWithBankTransfer(
            [
                'user_id' => $order->userId,
                'item_id' => $item['item_id'],
                'delivery_name' => $order->deliveryName,
                'delivery_prefecture' => $order->deliveryPrefecture,
                'delivery_city' => $order->deliveryCity,
                'delivery_tell' => $order->deliveryTell,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'payment_method' => $order->paymentMethod->value,
                'shipping_fee' => $order->shippingFee,
                'total_price' => $order->totalPrice,
            ],
            $this->bankTransferRow($order),
        );
    }

    /**
     * 保存した予約のサンクスメールを作る（表示に使う会員・商品・振込先を先に読み込む）
     *
     * @param  Reservation  $saved
     */
    #[Override]
    protected function thanksMail(Model $saved): ThanksMail
    {
        return new ReservationThanksMail($this->reservationRepository->loadForThanksMail($saved));
    }
}
