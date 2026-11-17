<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Delivery_address;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * 公開デモ用のサンプルデータ（会員・注文・予約・お問い合わせ）
 *
 * `db:seed --class=DemoSeeder` で明示したときだけ実行する（DatabaseSeeder からは呼ばない）。
 * DatabaseSeeder（商品・都道府県などの初期データ）の後に実行する前提で、公開デモの毎日の作り直し（scripts/demo/reset.sh）が呼ぶ。
 * 本番のイメージは composer install --no-dev で作るため、faker（開発用の依存）・ファクトリは使わず、固定のデータで作る。
 *
 * - 会員10人（架空の氏名・住所。メールアドレスは example.com。パスワードはすべて PASSWORD）
 * - 注文24件・予約6件（日付は過去2か月に分散。支払方法の card・customer_balance・cod、発送済み・未発送・取消済みを混ぜる）
 * - お問い合わせ5件
 *
 * 金額は実際の保存処理と同じく ShippingService（config/shipping.php）で計算し、合計 = 小計 + 送料 + 代引手数料にする。
 * Stripe の ID（銀行振込の PaymentIntent）は pi_demo_ から始まるダミー値。Stripe には存在しないため、管理画面の取消で Stripe に問い合わせる
 * 「未発送・未取消の銀行振込」は作らない（銀行振込は発送済みか取消済みだけ。取消済みは Stripe を呼ばない）。
 * すでにデモの会員（メールアドレスが demo01@example.com）がいるときは何もしない（二重に作らない）。
 */
class DemoSeeder extends Seeder
{
    /** 会員のパスワード（すべて同じ。公開してよい架空のアカウントのため） */
    public const string PASSWORD = 'demo1234';

    /** 会員の数 */
    public const int USER_COUNT = 10;

    /** 注文の数 */
    public const int PURCHASE_COUNT = 24;

    /** 予約の数 */
    public const int RESERVATION_COUNT = 6;

    /** お問い合わせの数 */
    public const int INQUIRY_COUNT = 5;

    /**
     * 会員（姓・名・都道府県・市区町村以下）。メールアドレスは demo01〜、電話番号は連番で作る
     *
     * @var list<array{string, string, string, string}>
     */
    private const array USERS = [
        ['青木', '一郎', '北海道', '札幌市中央区サンプル町1-1'],
        ['石田', '花子', '宮城県', '仙台市青葉区サンプル町2-2'],
        ['上野', '健太', '東京都', '千代田区サンプル町3-3'],
        ['遠藤', '美咲', '神奈川県', '横浜市中区サンプル町4-4'],
        ['岡田', '翔太', '愛知県', '名古屋市中区サンプル町5-5'],
        ['加藤', 'さくら', '大阪府', '大阪市北区サンプル町6-6'],
        ['木村', '大輔', '広島県', '広島市中区サンプル町7-7'],
        ['小林', '陽子', '福岡県', '福岡市中央区サンプル町8-8'],
        ['斎藤', '拓也', '新潟県', '新潟市中央区サンプル町9-9'],
        ['須藤', '千春', '沖縄県', '那覇市サンプル町10-10'],
    ];

    /**
     * 注文（古い順）：[会員の番号（1〜）, 何日前, 支払方法, 状態, 商品の行[[商品キー, 数量], ...], 残高払いか（省略可。省略は false）]
     * 状態：shipped（発送済み）・pending（未発送）・canceled（取消済み）。商品キーは「商品名 重さ」。
     * 残高払い：true は、銀行振込で、Stripe の顧客残高で支払い済みだった注文（振込先の5項目が null）。銀行振込の注文だけに指定できる
     *
     * @var list<array{0: int, 1: int, 2: string, 3: string, 4: list<array{string, int}>, 5?: bool}>
     */
    private const array PURCHASES = [
        [1, 58, 'card', 'shipped', [['じゃがいも 2', 2]]],
        [2, 55, 'cod', 'shipped', [['玉ねぎ 5', 1], ['人参 2', 1]]],
        [3, 52, 'customer_balance', 'shipped', [['じゃがいも 10', 1]]],
        [4, 50, 'card', 'shipped', [['人参 5', 2]]],
        [5, 47, 'cod', 'shipped', [['じゃがいも 5', 1], ['玉ねぎ 5', 1], ['人参 5', 1]]],
        [6, 45, 'card', 'canceled', [['玉ねぎ 10', 1]]],
        [7, 42, 'customer_balance', 'shipped', [['玉ねぎ 2', 3]]],
        [8, 40, 'cod', 'shipped', [['人参 10', 1]]],
        [9, 38, 'card', 'shipped', [['じゃがいも 2', 1], ['人参 2', 2]]],
        [10, 35, 'customer_balance', 'shipped', [['じゃがいも 5', 2]]],
        [1, 33, 'customer_balance', 'canceled', [['玉ねぎ 5', 2]]],
        [2, 31, 'cod', 'canceled', [['人参 5', 1]]],
        [3, 28, 'card', 'shipped', [['じゃがいも 10', 2], ['玉ねぎ 10', 1]]],
        [4, 25, 'cod', 'shipped', [['玉ねぎ 2', 2]]],
        [5, 22, 'customer_balance', 'shipped', [['人参 10', 2]], true],
        [6, 19, 'card', 'shipped', [['じゃがいも 5', 1]]],
        [7, 16, 'cod', 'pending', [['玉ねぎ 5', 3]]],
        [8, 13, 'card', 'pending', [['じゃがいも 2', 3], ['人参 5', 1]]],
        [9, 11, 'cod', 'pending', [['人参 2', 1]]],
        [10, 8, 'card', 'pending', [['玉ねぎ 10', 2]]],
        [1, 6, 'card', 'canceled', [['じゃがいも 5', 1]]],
        [2, 4, 'cod', 'pending', [['じゃがいも 10', 1]]],
        [3, 2, 'card', 'pending', [['玉ねぎ 2', 1], ['人参 2', 1], ['じゃがいも 2', 1]]],
        [4, 1, 'cod', 'pending', [['人参 5', 2]]],
    ];

    /**
     * 予約（古い順）：[会員の番号, 何日前, 支払方法, 状態, 商品キー, 数量]
     *
     * @var list<array{int, int, string, string, string, int}>
     */
    private const array RESERVATIONS = [
        [5, 50, 'card', 'shipped', '北の恵みセット 5', 2],
        [6, 41, 'customer_balance', 'shipped', '北の恵みセット 10', 1],
        [7, 30, 'card', 'shipped', '北の恵みセット 5', 1],
        [8, 14, 'card', 'pending', '北の恵みセット 10', 2],
        [9, 7, 'card', 'pending', '北の恵みセット 5', 3],
        [10, 3, 'card', 'canceled', '北の恵みセット 10', 1],
    ];

    /**
     * お問い合わせ：[姓, 名, メールアドレス, 内容, 対応済みか, 何日前]
     *
     * @var list<array{string, string, string, string, bool, int}>
     */
    private const array INQUIRIES = [
        ['青木', '一郎', 'demo01@example.com', '商品の配送日を教えてください。', true, 40],
        ['岡田', '翔太', 'demo05@example.com', '予約した商品の内容を変更したいです。', true, 21],
        ['木村', '大輔', 'demo07@example.com', '農園の見学はできますか。', false, 9],
        ['須藤', '千春', 'demo10@example.com', '代金引換の手数料について教えてください。', false, 4],
        ['小林', '陽子', 'demo08@example.com', 'まとめて注文した場合、送料は安くなりますか。', false, 1],
    ];

    /**
     * 予約の作成時刻（createdAt の時・分をずらす連番）に足す値。予約の時刻が、同じ日の注文と同じ並びにならないようにずらすだけで、意味のある値ではない
     */
    private const int RESERVATION_TIME_SHIFT = 3;

    /** 銀行振込のダミーの振込先 */
    private const array BANK = [
        'bank_name' => 'デモ銀行',
        'branch_name' => 'テスト支店',
        'account_type' => 'futsu',
        'account_number' => '1234567',
        'account_holder_name' => 'シオヤノウエン（デモ）',
    ];

    /**
     * 作ったダミーの PaymentIntent ID の連番
     */
    private int $intentSequence = 0;

    /**
     * Run the database seeds.
     *
     * @throws RuntimeException DatabaseSeeder の初期データ（商品）がないとき
     */
    public function run(ShippingService $shipping): void
    {
        if (User::withTrashed()->where('email', 'demo01@example.com')->exists()) {
            return;
        }

        /** @var array<string, Item> $items */
        $items = [];
        foreach (Item::query()->get() as $item) {
            $items[$item->name.' '.$item->weight] = $item;
        }
        if ($items === []) {
            throw new RuntimeException('商品がありません。先に DatabaseSeeder を実行してください。');
        }

        DB::transaction(function () use ($shipping, $items): void {
            $users = $this->createUsers();
            $this->createPurchases($shipping, $items, $users);
            $this->createReservations($shipping, $items, $users);
            $this->createInquiries();
        });
    }

    /**
     * 会員と、一部の会員の追加の配送先住所を作る
     *
     * @return list<User>
     */
    private function createUsers(): array
    {
        $password = Hash::make(self::PASSWORD);
        $users = [];

        foreach (self::USERS as $index => [$lastName, $firstName, $prefecture, $city]) {
            $number = $index + 1;
            $createdAt = now()->subDays(70 - $number)->setTime(10, 0);

            $user = new User;
            $user->forceFill([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'prefecture' => $prefecture,
                'city' => $city,
                'tell' => sprintf('0901234%04d', $number),
                'email' => sprintf('demo%02d@example.com', $number),
                'password' => $password,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
            $users[] = $user;
        }

        // 会員の追加の配送先住所（マイページの住所の一覧用。先頭の3人だけ）
        foreach (array_slice($users, 0, 3) as $user) {
            Delivery_address::create([
                'user_id' => $user->id,
                'delivery_prefecture' => '北海道',
                'delivery_city' => '札幌市北区サンプル町11-11',
            ]);
        }

        return $users;
    }

    /**
     * 注文（明細・銀行振込の振込先つき）を古い順に作る
     *
     * @param  array<string, Item>  $items
     * @param  list<User>  $users
     */
    private function createPurchases(ShippingService $shipping, array $items, array $users): void
    {
        foreach (self::PURCHASES as $index => $row) {
            [$userNumber, $daysAgo, $method, $status, $lines] = $row;
            $paidByBalance = $row[5] ?? false;
            $user = $users[$userNumber - 1];
            $createdAt = $this->createdAt($daysAgo, $index);

            $rows = [];
            $subtotal = 0;
            $weight = 0;
            foreach ($lines as [$key, $quantity]) {
                $item = $this->item($items, $key);
                $rows[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'item_weight' => $item->weight,
                    'quantity' => $quantity,
                    'price' => $item->price,
                    'subtotal' => $item->price * $quantity,
                ];
                $subtotal += $item->price * $quantity;
                $weight += $item->weight * $quantity;
            }
            $shippingFee = $shipping->calculate($user->prefecture, $weight);
            $codFee = $method === PaymentMethod::Cod->value ? (int) config('shipping.cod_fee') : 0;
            $total = $subtotal + $shippingFee + $codFee;

            $purchase = new Purchase_history;
            $purchase->forceFill([
                'user_id' => $user->id,
                'delivery_name' => $user->full_name,
                'delivery_prefecture' => $user->prefecture,
                'delivery_city' => $user->city,
                'delivery_tell' => $user->tell,
                'payment_method' => $method,
                'shipping_fee' => $shippingFee,
                'cod_fee' => $codFee,
                'total_price' => $total,
                ...$this->statusColumns($status, $createdAt),
                'created_at' => $createdAt,
                'updated_at' => $this->updatedAt($status, $createdAt),
            ])->save();
            $purchase->purchaseHistoryItems()->createMany($rows);

            if ($method === PaymentMethod::CustomerBalance->value) {
                // 振込先の行（Stripe の顧客残高で支払い済みだった注文は、振込先の5項目が null）
                $purchase->purchaseBankTransfer()->create($this->bankTransferRow($total, $paidByBalance));
            }
        }
    }

    /**
     * 予約を古い順に作る（銀行振込の振込先つき。予約は代金引換を選べず、代引手数料の列もない）
     *
     * @param  array<string, Item>  $items
     * @param  list<User>  $users
     */
    private function createReservations(ShippingService $shipping, array $items, array $users): void
    {
        foreach (self::RESERVATIONS as $index => [$userNumber, $daysAgo, $method, $status, $key, $quantity]) {
            $user = $users[$userNumber - 1];
            $item = $this->item($items, $key);
            $createdAt = $this->createdAt($daysAgo, $index + self::RESERVATION_TIME_SHIFT);

            $subtotal = $item->price * $quantity;
            $shippingFee = $shipping->calculate($user->prefecture, $item->weight * $quantity);
            $total = $subtotal + $shippingFee;

            $reservation = new Reservation;
            $reservation->forceFill([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_weight' => $item->weight,
                'delivery_name' => $user->full_name,
                'delivery_prefecture' => $user->prefecture,
                'delivery_city' => $user->city,
                'delivery_tell' => $user->tell,
                'quantity' => $quantity,
                'price' => $item->price,
                'subtotal' => $subtotal,
                'payment_method' => $method,
                'shipping_fee' => $shippingFee,
                'total_price' => $total,
                ...$this->statusColumns($status, $createdAt),
                'created_at' => $createdAt,
                'updated_at' => $this->updatedAt($status, $createdAt),
            ])->save();

            if ($method === PaymentMethod::CustomerBalance->value) {
                $reservation->reservationBankTransfer()->create($this->bankTransferRow($total, false));
            }
        }
    }

    /**
     * お問い合わせを作る
     */
    private function createInquiries(): void
    {
        foreach (self::INQUIRIES as [$lastName, $firstName, $email, $detail, $addressed, $daysAgo]) {
            $createdAt = now()->subDays($daysAgo)->setTime(15, 30);

            $inquiry = new Inquiry;
            $inquiry->forceFill([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'email' => $email,
                'detail' => $detail,
                'addressed' => $addressed,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
    }

    /**
     * 商品キー（「商品名 重さ」）から商品を取り出す
     *
     * @param  array<string, Item>  $items
     *
     * @throws RuntimeException 商品がないとき
     */
    private function item(array $items, string $key): Item
    {
        return $items[$key] ?? throw new RuntimeException("商品が見つかりません：{$key}");
    }

    /**
     * 作成日時（何日前の、注文ごとに少しずらした時刻）
     */
    private function createdAt(int $daysAgo, int $index): Carbon
    {
        return now()->subDays($daysAgo)->setTime(9 + $index % 9, ($index * 7) % 60);
    }

    /**
     * 更新日時（発送済み・取消済みは、作成の少し後）
     */
    private function updatedAt(string $status, Carbon $createdAt): Carbon
    {
        return $status === 'pending' ? $createdAt : $createdAt->copy()->addHours(5);
    }

    /**
     * 状態（shipped・pending・canceled）に対応する shipped・canceled_at の値
     *
     * @return array{shipped: bool, canceled_at: Carbon|null}
     */
    private function statusColumns(string $status, Carbon $createdAt): array
    {
        return [
            'shipped' => $status === 'shipped',
            'canceled_at' => $status === 'canceled' ? $createdAt->copy()->addHours(5) : null,
        ];
    }

    /**
     * 銀行振込の振込先の行（ダミーの PaymentIntent ID つき）
     *
     * @return array<string, mixed>
     */
    private function bankTransferRow(int $amount, bool $paidByBalance): array
    {
        $this->intentSequence++;

        return [
            'stripe_payment_intent_id' => sprintf('pi_demo_%04d', $this->intentSequence),
            ...($paidByBalance
                ? ['bank_name' => null, 'branch_name' => null, 'account_type' => null, 'account_number' => null, 'account_holder_name' => null]
                : self::BANK),
            'amount' => $amount,
        ];
    }
}
