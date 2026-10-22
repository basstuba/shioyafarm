<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Item;
use App\Models\User;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Override;
use Throwable;

/**
 * 購入サービス
 *
 * 購入ページの金額計算、購入の注文の作成（カート・商品詳細）、購入完了時の保存を担当する。
 * 「注文を確定する」（決済ページへの遷移）・銀行振込・二重送信の対策は CheckoutService にある（予約と共通）。
 * 金額はフォームの値を信用せず、DBの価格と config/shipping.php から再計算する。
 */
class PurchaseService extends CheckoutService
{
    /**
     * 購入ページで保存待ちの注文の入力を復元する合図（セッションのフラッシュのキー）
     *
     * 復元するのは、決済ページ（Stripe Checkout）の「戻る」や振込先のご案内の「戻る」で戻った直後の1回だけ。
     * 通常の訪問では会員登録の内容で表示する。
     */
    public const string RESTORE_FLASH_KEY = 'restore_pending_order';

    /** サンクスページ（銀行振込・代金引換）を「保存した直後の1回だけ」表示するための合図（セッションのフラッシュのキー） */
    public const string THANKS_FLASH_KEY = 'purchase_completed';

    /** 別のリクエストが処理中のときのエラーメッセージ（注文は作られていないので、もう一度押してよい） */
    private const string BUSY_MESSAGE = 'ただいまご注文を処理しています。しばらく待ってから、もう一度お試しください';

    /** 支払い済みの銀行振込を保存できなかったときのエラーメッセージ（押し直しは促さない。保存待ちの注文は残してあり、購入ページを開き直したときに保存する） */
    public const string PAID_PENDING_MESSAGE = 'お支払いは完了しています。ただいま確定処理中のため、しばらくしてから購入ページを開き直してください';

    public function __construct(
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly PurchaseHistoryRepositoryInterface $purchaseHistoryRepository,
        private readonly CartService $cartService,
        private readonly ShippingService $shippingService,
        private readonly DeliveryAddressService $deliveryAddressService,
        PaymentGatewayInterface $paymentGateway,
        PendingOrderStore $pendingOrderStore,
        UserRepositoryInterface $userRepository,
        OrderTokenStore $orderTokenStore,
    ) {
        parent::__construct($paymentGateway, $pendingOrderStore, $userRepository, $orderTokenStore);
    }

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

    #[Override]
    protected function lockNamespace(): string
    {
        return 'purchase';
    }

    #[Override]
    protected function thanksRoute(): string
    {
        return 'shop.checkout.thanks';
    }

    #[Override]
    protected function successRoute(): string
    {
        return 'shop.checkout.success';
    }

    #[Override]
    protected function bankTransferRoute(): string
    {
        return 'shop.checkout.bank-transfer';
    }

    #[Override]
    protected function cancelUrl(PendingOrder $order): string
    {
        return route('shop.checkout.index', array_filter(['item' => $order->itemId(), 'restore' => 1]));
    }

    #[Override]
    protected function staleRedirectUrl(array $data): string
    {
        /** @var array{source: string, items: list<array{item_id: int|string}>} $data */
        $itemId = $data['source'] === PurchaseSource::Item->value ? (int) ($data['items'][0]['item_id'] ?? 0) : null;

        return route('shop.checkout.index', $itemId > 0 ? $itemId : null);
    }

    #[Override]
    protected function busyMessage(): string
    {
        return self::BUSY_MESSAGE;
    }

    #[Override]
    protected function paidPendingMessage(): string
    {
        return self::PAID_PENDING_MESSAGE;
    }

    /**
     * 保存後の片づけ：保存待ちの注文を消し、カートから購入した場合は購入した商品の数量だけをカートから減らす
     *
     * カートを空にはしない（購入していない商品や、前の注文を保存する間に入れ替わった新しい注文の商品を消さないため）。
     */
    #[Override]
    protected function finish(PendingOrder $order): void
    {
        parent::finish($order);

        if ($order->source === PurchaseSource::Cart) {
            $purchased = [];
            foreach ($order->items as $item) {
                $purchased[$item['item_id']] = ($purchased[$item['item_id']] ?? 0) + $item['quantity'];
            }
            $this->cartService->reduceQuantities($purchased);
        }
    }

    /**
     * 検証済みの入力から、金額を再計算した保存待ちの注文を作る
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    #[Override]
    protected function createPendingOrder(User $user, array $data): PendingOrder
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
     * 保存待ちの注文を購入履歴・購入明細（銀行振込のときは振込先も）として保存する
     *
     * @throws Throwable 保存に失敗したとき
     */
    #[Override]
    protected function save(PendingOrder $order): void
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
