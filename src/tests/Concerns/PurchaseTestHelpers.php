<?php

namespace Tests\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseSource;
use App\Models\Area;
use App\Models\Item;
use App\Models\User;
use App\Services\OrderTokenStore;
use App\Services\Payment\BankTransferInstructions;
use App\Services\PendingOrder;
use Mockery\MockInterface;

/**
 * 購入機能のテストで使う、商品・会員・都道府県を作る補助
 */
trait PurchaseTestHelpers
{
    /**
     * 購入できる商品を作る
     */
    private function makeItem(string $name, int $weight, int $price): Item
    {
        return Item::factory()->create([
            'name' => $name,
            'weight' => $weight,
            'price' => $price,
            'img_url' => 'image/product/potato-card.webp',
            'is_reserved_only' => false,
        ]);
    }

    /**
     * 予約限定の商品を作る
     */
    private function makeReservedItem(): Item
    {
        return Item::factory()->create([
            'name' => '北の恵みセット',
            'weight' => 10,
            'price' => 5000,
            'is_reserved_only' => true,
            'reservation_limit' => 200,
        ]);
    }

    /**
     * 会員を作る（住所は北海道、電話番号はハイフンなし）
     */
    private function makeUser(string $prefecture = '北海道'): User
    {
        return User::factory()->create([
            'last_name' => '塩谷',
            'first_name' => '太郎',
            'prefecture' => $prefecture,
            'city' => '札幌市中央区1-1-1',
            'tell' => '09012345678',
        ]);
    }

    /**
     * 都道府県（areas）を作る
     *
     * @param  list<string>  $prefectures
     */
    private function makeAreas(array $prefectures = ['北海道', '東京都', '沖縄県']): void
    {
        foreach ($prefectures as $prefecture) {
            Area::create(['prefecture' => $prefecture]);
        }
    }

    /**
     * 「注文を確定する」で送る値（PurchaseRequest が受け取る形）を組み立てる
     *
     * @param  array<int, int>  $quantities  商品ID => 数量
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paymentData(User $user, array $quantities, array $overrides = []): array
    {
        $items = [];
        foreach ($quantities as $itemId => $quantity) {
            $items[] = ['item_id' => $itemId, 'quantity' => $quantity];
        }

        return array_merge([
            'source' => 'cart',
            'items' => $items,
            'delivery_name' => '塩谷太郎',
            'address_id' => 'user_'.$user->id,
            'delivery_tell' => '09012345678',
            'payment_method' => 'card',
            // 購入ページが発行する1回限りの注文トークン（呼ぶたびに新しく発行する。同じ内容を2回送るときは、2回呼ぶ）
            'order_token' => $this->newOrderToken($user),
        ], $overrides);
    }

    /**
     * 購入ページが発行する1回限りの注文トークンを新しく発行する
     */
    private function newOrderToken(User $user): string
    {
        return app(OrderTokenStore::class)->issue($user->id);
    }

    /**
     * 決済サービスのモックに、Stripe Customer の用意（ensureCustomer）を何度呼ばれてもよい形で設定する
     *
     * 保存済みの Customer ID があればそのまま返し、無ければ指定した ID（既定は cus_test）を返す。
     */
    private function stubCustomer(MockInterface $mock, string $customerId = 'cus_test'): void
    {
        $mock->shouldReceive('ensureCustomer')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (string $email, ?string $existing): string => $existing ?? $customerId);
    }

    /**
     * シナリオ S-2 のカート（じゃがいも2kg×2、玉ねぎ5kg×1、人参2kg×1。北海道で送料1,850円・合計3,723円）を作る
     *
     * @return array{User, array<string, Item>}
     */
    private function makeCartScenario(): array
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $items = [
            'potato' => $this->makeItem('じゃがいも', 2, 330),
            'onion' => $this->makeItem('玉ねぎ', 5, 795),
            'carrot' => $this->makeItem('人参', 2, 418),
        ];
        session(['cart' => [$items['potato']->id => 2, $items['onion']->id => 1, $items['carrot']->id => 1]]);

        return [$user, $items];
    }

    /**
     * 保存待ちの注文（makeCartScenario の内容、北海道）をセッションに入れて返す
     *
     * paymentIntentId を渡すと、案内ページに表示する振込先（いろは銀行・合計3,723円）も付ける。
     *
     * @param  array<string, Item>  $items
     */
    private function putPending(
        User $user,
        array $items,
        PaymentMethod $method,
        ?string $sessionId = null,
        ?string $paymentIntentId = null,
        PurchaseSource $source = PurchaseSource::Cart,
        ?string $orderId = null,
    ): PendingOrder {
        $codFee = $method === PaymentMethod::Cod ? 330 : 0;

        $order = new PendingOrder(
            $user->id,
            $source,
            'user_'.$user->id,
            '山田花子',
            '08011112222',
            '北海道',
            '札幌市中央区1-1-1',
            $method,
            [
                ['item_id' => $items['potato']->id, 'name' => 'じゃがいも 2kg', 'quantity' => 2, 'price' => 330, 'subtotal' => 660],
                ['item_id' => $items['onion']->id, 'name' => '玉ねぎ 5kg', 'quantity' => 1, 'price' => 795, 'subtotal' => 795],
                ['item_id' => $items['carrot']->id, 'name' => '人参 2kg', 'quantity' => 1, 'price' => 418, 'subtotal' => 418],
            ],
            1873,
            1850,
            $codFee,
            1873 + 1850 + $codFee,
            $sessionId,
            $paymentIntentId,
            $paymentIntentId === null ? null : new BankTransferInstructions('いろは銀行', '中央支店', 'futsu', '1234567', 'ストライプジャパン', 3723),
            $orderId,
        );
        session(['pending_order' => $order->toArray()]);

        return $order;
    }
}
