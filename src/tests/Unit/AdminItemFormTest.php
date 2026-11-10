<?php

namespace Tests\Unit;

use App\Http\Requests\ItemStoreRequest;
use App\Models\Item;
use App\View\Components\AdminItemForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminItemForm（管理画面の掲載商品の登録・修正フォーム）の初期値とクラスの組み立てのテスト
 *
 * フォームの出力は tests/Feature/AdminItemFormTest と AdminItemFormComponentTest（Blade::render）で確認している。
 */
class AdminItemFormTest extends TestCase
{
    /**
     * 直前の入力（フラッシュされた old）を設定する
     *
     * @param  array<string, mixed>  $input
     */
    private function flashOld(array $input): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', $input);
    }

    /**
     * DBに保存せずに商品を作る
     *
     * @param  array<string, mixed>  $attributes
     */
    private function item(array $attributes = []): Item
    {
        return (new Item)->forceFill([
            'id' => 7,
            'name' => 'じゃがいも',
            'weight' => 5,
            'price' => 1500,
            'img_url' => 'image/product/potato.webp',
            'detail' => '紹介文です',
            'recommend' => true,
            'is_reserved_only' => false,
            'reservation_limit' => null,
            ...$attributes,
        ]);
    }

    /**
     * 登録は入力欄が空で、ラジオはすべて未選択、画像もない
     */
    #[Test]
    public function 登録の初期値は空で未選択(): void
    {
        $form = new AdminItemForm('create');

        $this->assertFalse($form->isEdit());
        $this->assertSame('', $form->name);
        $this->assertSame('', $form->weight);
        $this->assertSame('', $form->price);
        $this->assertSame('', $form->detail);
        $this->assertSame('', $form->recommend);
        $this->assertSame('', $form->reservedOnly);
        $this->assertSame('', $form->limitType);
        $this->assertSame('', $form->reservationLimit);
        $this->assertSame('', $form->currentUrl);
        $this->assertSame(route('admin.items.store'), $form->action());
        $this->assertSame('登録する', $form->submitLabel());
    }

    /**
     * 修正は商品の今の値を初期値にする（通常の商品）
     */
    #[Test]
    public function 修正の初期値は商品の今の値(): void
    {
        $form = new AdminItemForm('edit', $this->item());

        $this->assertTrue($form->isEdit());
        $this->assertSame('じゃがいも', $form->name);
        $this->assertSame('5', $form->weight);
        $this->assertSame('1500', $form->price);
        $this->assertSame('紹介文です', $form->detail);
        $this->assertSame('1', $form->recommend);
        $this->assertSame('0', $form->reservedOnly);
        $this->assertSame('', $form->limitType);
        $this->assertSame('', $form->reservationLimit);
        $this->assertSame(asset('storage/image/product/potato.webp'), $form->currentUrl);
        $this->assertSame(route('admin.items.update'), $form->action());
        $this->assertSame('修正する', $form->submitLabel());
    }

    /**
     * おすすめでない商品は「いいえ」（'0'）になる
     */
    #[Test]
    public function おすすめでない商品はいいえになる(): void
    {
        $form = new AdminItemForm('edit', $this->item(['recommend' => false]));

        $this->assertSame('0', $form->recommend);
    }

    /**
     * 予約可能数の種類：予約限定でなければ空、限定で上限なし・上限ありはそれぞれ
     *
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function limitTypes(): array
    {
        return [
            '通常' => [['is_reserved_only' => false, 'reservation_limit' => null], '0', '', ''],
            '通常で上限が残っていても無視する' => [['is_reserved_only' => false, 'reservation_limit' => 30], '0', '', '30'],
            '限定で上限なし' => [['is_reserved_only' => true, 'reservation_limit' => null], '1', ItemStoreRequest::LIMIT_UNLIMITED, ''],
            '限定で上限あり' => [['is_reserved_only' => true, 'reservation_limit' => 30], '1', ItemStoreRequest::LIMIT_LIMITED, '30'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[Test]
    #[DataProvider('limitTypes')]
    public function 修正の予約限定と予約可能数の種類は商品から決まる(array $attributes, string $reservedOnly, string $limitType, string $limit): void
    {
        $form = new AdminItemForm('edit', $this->item($attributes));

        $this->assertSame($reservedOnly, $form->reservedOnly);
        $this->assertSame($limitType, $form->limitType);
        $this->assertSame($limit, $form->reservationLimit);
    }

    /**
     * 登録で old があるときは old の値を使う（入力エラー・419で戻ったとき）
     */
    #[Test]
    public function 登録でoldがあればoldの値になる(): void
    {
        $this->flashOld([
            'name' => 'あ<b>',
            'weight' => '10',
            'price' => '330',
            'detail' => "1行目\n2行目",
            'recommend' => '0',
            'is_reserved_only' => '1',
            'reservation_limit_type' => ItemStoreRequest::LIMIT_LIMITED,
            'reservation_limit' => '12',
        ]);

        $form = new AdminItemForm('create');

        $this->assertSame('あ<b>', $form->name);
        $this->assertSame('10', $form->weight);
        $this->assertSame('330', $form->price);
        $this->assertSame("1行目\n2行目", $form->detail);
        $this->assertSame('0', $form->recommend);
        $this->assertSame('1', $form->reservedOnly);
        $this->assertSame(ItemStoreRequest::LIMIT_LIMITED, $form->limitType);
        $this->assertSame('12', $form->reservationLimit);
        $this->assertSame('', $form->currentUrl);
    }

    /**
     * 修正で old があるときは、商品の今の値より old を優先し、old にない項目は商品の値のまま
     */
    #[Test]
    public function 修正でoldは商品の今の値より優先される(): void
    {
        $this->flashOld(['name' => '変更した名前', 'is_reserved_only' => '1', 'reservation_limit_type' => ItemStoreRequest::LIMIT_UNLIMITED]);

        $form = new AdminItemForm('edit', $this->item());

        $this->assertSame('変更した名前', $form->name);
        $this->assertSame('1', $form->reservedOnly);
        $this->assertSame(ItemStoreRequest::LIMIT_UNLIMITED, $form->limitType);
        $this->assertSame('5', $form->weight);
        $this->assertSame('1500', $form->price);
        $this->assertSame(asset('storage/image/product/potato.webp'), $form->currentUrl);
    }

    /**
     * 配列など文字列にできない old（改変した送信）は空になる
     */
    #[Test]
    public function 文字列にできないoldは空になる(): void
    {
        $this->flashOld(['name' => ['x'], 'weight' => ['5']]);

        $form = new AdminItemForm('create');

        $this->assertSame('', $form->name);
        $this->assertSame('', $form->weight);
    }

    /**
     * 登録以外の mode は登録と同じ（修正だけ edit）
     */
    #[Test]
    public function editでなければ登録として扱う(): void
    {
        $this->assertFalse((new AdminItemForm('other'))->isEdit());
        $this->assertSame('登録する', (new AdminItemForm)->submitLabel());
    }

    /**
     * 送信ボタンの色：登録は橙の地、修正は青の地
     */
    #[Test]
    public function 送信ボタンのクラスは登録と修正で変わる(): void
    {
        $create = (new AdminItemForm('create'))->submitClass();
        $edit = (new AdminItemForm('edit', $this->item()))->submitClass();

        $this->assertStringContainsString('bg-primary text-ink', $create);
        $this->assertStringContainsString('hover:bg-primary-dark', $create);
        $this->assertStringNotContainsString('mail-link', $create);
        $this->assertStringContainsString('bg-mail-link text-white', $edit);
        $this->assertStringContainsString('focus-visible:bg-mail-link-hover', $edit);
        $this->assertStringNotContainsString('bg-primary', $edit);
    }

    /**
     * 枠線の色：エラーのある項目は赤
     */
    #[Test]
    public function エラーのある項目の枠線は赤になる(): void
    {
        $form = new AdminItemForm;

        $this->assertSame('border-danger', $form->borderClass(true));
        $this->assertSame('border-line-strong', $form->borderClass(false));
    }
}
