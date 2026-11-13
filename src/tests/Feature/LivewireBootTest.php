<?php

namespace Tests\Feature;

use App\Livewire\PurchaseForm;
use App\Models\Delivery_address;
use App\Models\Purchase_history;
use App\Models\User;
use App\Services\DeliveryAddressService;
use App\Support\LivewireBoot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\MyPageTestHelpers;
use Tests\Concerns\PurchaseTestHelpers;
use Tests\TestCase;

/**
 * Livewire が起動する前に押せないボタン・入力欄（LivewireBoot）と、購入ページの数量のエラー表示のテスト
 */
class LivewireBootTest extends TestCase
{
    use MyPageTestHelpers;
    use PurchaseTestHelpers;
    use RefreshDatabase;

    /**
     * HTML から、指定の文字列を含む <button>／<input> の開始タグを取り出す
     */
    private function tagWith(string $html, string $needle): string
    {
        $this->assertSame(1, preg_match('#<(?:button|input)\b[^>]*'.preg_quote($needle, '#').'[^>]*>#s', $html, $matches), $needle.' を含む要素がない');

        return $matches[0];
    }

    /**
     * HTML から、指定の文字列を含む <select> の開始タグを取り出す
     */
    private function tagWithSelect(string $html, string $needle): string
    {
        $this->assertSame(1, preg_match('#<select\b[^>]*'.preg_quote($needle, '#').'[^>]*>#s', $html, $matches), $needle.' を含む select がない');

        return $matches[0];
    }

    /**
     * 起動前の disabled と、起動後に外す Alpine の属性（x-data・x-bind:disabled="false"）が付く
     */
    #[Test]
    public function 既定では起動前のdisabledと起動後に外す属性が付く(): void
    {
        $html = '<button '.LivewireBoot::attributes()->toHtml().'>x</button>';

        $this->assertStringContainsString('disabled="disabled"', $html);
        $this->assertStringContainsString('x-data=""', $html);
        $this->assertStringContainsString('x-bind:disabled="false"', $html);
    }

    /**
     * もともと押せない条件のときは、起動しても外さない（x-bind:disabled="true"）
     */
    #[Test]
    public function もともと押せない条件のときは起動しても外さない(): void
    {
        $html = '<button '.LivewireBoot::attributes(true)->toHtml().'>x</button>';

        $this->assertStringContainsString('disabled="disabled"', $html);
        $this->assertStringContainsString('x-bind:disabled="true"', $html);
    }

    /**
     * 起動後の Livewire の更新リクエストの描画では、押せる条件の disabled を付けない（付けると morph で押せなくなる）。押せない条件は付ける
     */
    #[Test]
    public function 更新リクエストの描画では押せる条件のdisabledを付けない(): void
    {
        request()->headers->set('X-Livewire', 'true');

        $enabled = LivewireBoot::attributes()->toHtml();
        $this->assertStringNotContainsString('disabled="disabled"', $enabled);
        $this->assertStringContainsString('x-bind:disabled="false"', $enabled);

        $this->assertStringContainsString('disabled="disabled"', LivewireBoot::attributes(true)->toHtml());
    }

    /**
     * 入力欄用は disabled ではなく readonly（値が送信される）。起動後に外す Alpine の属性が付く
     */
    #[Test]
    public function 入力欄は起動前にreadonlyでdisabledは付かない(): void
    {
        $html = '<input '.LivewireBoot::inputAttributes()->toHtml().'>';

        $this->assertStringContainsString('readonly="readonly"', $html);
        $this->assertStringContainsString('x-data=""', $html);
        $this->assertStringContainsString('x-bind:readonly="false"', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    /**
     * 起動後の Livewire の更新リクエストの描画では、入力欄に readonly を付けない（付けると morph で外した readonly が付き直される）
     */
    #[Test]
    public function 更新リクエストの描画では入力欄にreadonlyを付けない(): void
    {
        request()->headers->set('X-Livewire', 'true');

        $html = '<input '.LivewireBoot::inputAttributes()->toHtml().'>';

        $this->assertStringNotContainsString('readonly', str_replace('x-bind:readonly', '', $html));
        $this->assertStringContainsString('x-bind:readonly="false"', $html);
    }

    /**
     * ラジオボタン・選択欄用は disabled ではなく inert（値が送信される）。起動後に外す Alpine の属性が付く
     */
    #[Test]
    public function ラジオボタンと選択欄は起動前にinertでdisabledは付かない(): void
    {
        $html = '<input '.LivewireBoot::lockAttributes()->toHtml().'>';

        $this->assertStringContainsString('inert="inert"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('x-data=""', $html);
        $this->assertStringContainsString('x-bind:inert="false"', $html);
        $this->assertStringContainsString('x-bind:aria-disabled="false"', $html);
        $this->assertStringNotContainsString('disabled="disabled"', $html);
        $this->assertStringNotContainsString('readonly', $html);
    }

    /**
     * 起動後の Livewire の更新リクエストの描画では、inert・aria-disabled を付けない（付けると morph で外した属性が付き直される）
     */
    #[Test]
    public function 更新リクエストの描画ではinertを付けない(): void
    {
        request()->headers->set('X-Livewire', 'true');

        $html = '<input '.LivewireBoot::lockAttributes()->toHtml().'>';
        $stripped = str_replace(['x-bind:inert', 'x-bind:aria-disabled'], '', $html);

        $this->assertStringNotContainsString('inert', $stripped);
        $this->assertStringNotContainsString('aria-disabled', $stripped);
        $this->assertStringContainsString('x-bind:inert="false"', $html);
    }

    /**
     * x-select は lock を付けたときだけ起動前の inert になる
     */
    #[Test]
    public function 選択欄はlockを付けたときだけ起動前に変えられない(): void
    {
        $locked = Blade::render('<x-select name="a" wire:model.live="a" lock><option value="1">1</option></x-select>');
        $plain = Blade::render('<x-select name="a" wire:model.live="a"><option value="1">1</option></x-select>');

        $this->assertStringContainsString('inert="inert"', $locked);
        $this->assertStringContainsString('x-bind:inert="false"', $locked);
        $this->assertStringContainsString('wire:model.live="a"', $locked);
        $this->assertStringContainsString('inert:opacity-60', $locked);
        $this->assertStringNotContainsString('disabled="disabled"', $locked);
        $this->assertStringNotContainsString('inert', $plain);
    }

    /**
     * 購入ページの支払方法・お届け先住所は、起動前は変えられない（inert）が disabled ではなく、表示中の値が「注文を確定する」で送信される
     */
    #[Test]
    public function 購入ページの支払方法と住所は起動前に変えられず表示中の値が送信される(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $address = Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => '千代田区1-1']);
        $addressId = app(DeliveryAddressService::class)->deliveryAddressId($address);
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        session(['cart' => [$potato->id => 2, $onion->id => 1]]);

        // 確定でエラーになって戻ったあとのように、代金引換・追加した住所を表示させる
        $html = (string) $this->actingAs($user)
            ->withSession(['_old_input' => ['payment_method' => 'cod', 'address_id' => $addressId]])
            ->get(route('shop.checkout.index'))->assertOk()->getContent();

        // 支払方法：3つとも inert・aria-disabled で disabled ではない。表示中（checked）は代金引換
        preg_match_all('#<input\b[^>]*name="payment_method"[^>]*>#s', $html, $radios);
        $this->assertCount(3, $radios[0]);
        $checked = [];
        foreach ($radios[0] as $radio) {
            $this->assertStringContainsString('inert="inert"', $radio);
            $this->assertStringContainsString('aria-disabled="true"', $radio);
            $this->assertStringContainsString('x-bind:inert="false"', $radio);
            $this->assertStringNotContainsString('disabled="disabled"', $radio);
            if (preg_match('/\schecked\b/', $radio) === 1) {
                preg_match('/value="([^"]*)"/', $radio, $value);
                $checked[] = $value[1];
            }
        }
        $this->assertSame(['cod'], $checked);

        // 住所：選択欄も inert で disabled ではない。表示中（selected）は追加した住所
        $select = $this->tagWithSelect($html, 'name="address_id"');
        $this->assertStringContainsString('inert="inert"', $select);
        $this->assertStringContainsString('aria-disabled="true"', $select);
        $this->assertStringNotContainsString('disabled="disabled"', $select);
        $this->assertSame(1, preg_match('#<option value="([^"]*)"[^>]*\sselected[^>]*>#', $html, $selected));
        $this->assertSame($addressId, $selected[1]);

        // 起動前に「注文を確定する」（通常のフォーム送信）をすると、表示中の値が送信されて、その内容で注文される
        $this->post(route('shop.checkout.payment'), $this->paymentData($user, [$potato->id => 2, $onion->id => 1], [
            'payment_method' => $checked[0],
            'address_id' => $selected[1],
        ]))->assertRedirect(route('shop.checkout.thanks'));

        $history = Purchase_history::sole();
        $this->assertSame('cod', $history->payment_method);
        $this->assertSame('東京都', $history->delivery_prefecture);
        $this->assertSame('千代田区1-1', $history->delivery_city);
    }

    /**
     * 予約ページの支払方法・お届け先住所も、起動前は変えられない（inert）
     */
    #[Test]
    public function 予約ページの支払方法と住所は起動前に変えられない(): void
    {
        $this->makeAreas();
        $item = $this->makeReservedItem();

        $html = (string) $this->actingAs($this->makeUser())->get(route('shop.reservation.index', $item))->assertOk()->getContent();

        preg_match_all('#<input\b[^>]*name="payment_method"[^>]*>#s', $html, $radios);
        $this->assertNotEmpty($radios[0]);
        foreach ($radios[0] as $radio) {
            $this->assertStringContainsString('inert="inert"', $radio);
            $this->assertStringNotContainsString('disabled="disabled"', $radio);
        }
        $select = $this->tagWithSelect($html, 'name="address_id"');
        $this->assertStringContainsString('inert="inert"', $select);
        $this->assertStringNotContainsString('disabled="disabled"', $select);
    }

    /**
     * outline-button は livewire を付けたときだけ起動前の disabled になる。リンクには付かない
     */
    #[Test]
    public function アウトラインボタンはlivewireを付けたときだけ起動前に押せない(): void
    {
        $with = Blade::render('<x-outline-button type="button" livewire wire:click="go">追加</x-outline-button>');
        $without = Blade::render('<x-outline-button type="button" wire:click="go">追加</x-outline-button>');
        $link = Blade::render('<x-outline-button livewire href="/x">追加</x-outline-button>');

        $this->assertStringContainsString('disabled="disabled"', $with);
        $this->assertStringContainsString('x-bind:disabled="false"', $with);
        $this->assertStringContainsString('wire:click="go"', $with);
        $this->assertStringNotContainsString('disabled="disabled"', $without);
        $this->assertStringNotContainsString('x-bind:disabled', $link);
    }

    /**
     * livewire の outline-button に渡した disabled は、起動後も押せない条件として保つ。渡さない・false のときは起動後に外れる
     */
    #[Test]
    public function アウトラインボタンに渡したdisabledは起動後も保つ(): void
    {
        $always = Blade::render('<x-outline-button type="button" livewire disabled>追加</x-outline-button>');
        $true = Blade::render('<x-outline-button type="button" livewire :disabled="true">追加</x-outline-button>');
        $false = Blade::render('<x-outline-button type="button" livewire :disabled="false">追加</x-outline-button>');

        foreach ([$always, $true] as $html) {
            $this->assertStringContainsString('disabled="disabled"', $html);
            $this->assertStringContainsString('x-bind:disabled="true"', $html);
        }
        // false でも、起動前は disabled。起動後に外れる
        $this->assertStringContainsString('disabled="disabled"', $false);
        $this->assertStringContainsString('x-bind:disabled="false"', $false);

        // 更新リクエストの描画：押せる条件には disabled を付けず、渡された条件には付ける
        request()->headers->set('X-Livewire', 'true');
        $this->assertStringNotContainsString('disabled="disabled"', Blade::render('<x-outline-button type="button" livewire :disabled="false">追加</x-outline-button>'));
        $this->assertStringContainsString('disabled="disabled"', Blade::render('<x-outline-button type="button" livewire :disabled="true">追加</x-outline-button>'));
    }

    /**
     * outline-button に渡した disabled の判定：true・属性だけ・"disabled"・"true" は押せない。false・null・"false"・"0"・"" は押せる
     */
    #[Test]
    public function アウトラインボタンのdisabledは文字のfalseや0を押せないにしない(): void
    {
        $disabled = fn (string $attribute): bool => str_contains(
            Blade::render('<x-outline-button type="button" livewire '.$attribute.'>追加</x-outline-button>'),
            'x-bind:disabled="true"',
        );

        foreach (['disabled', ':disabled="true"', 'disabled="disabled"', 'disabled="true"', ':disabled="\'1\'"'] as $attribute) {
            $this->assertTrue($disabled($attribute), $attribute);
        }
        foreach ([':disabled="false"', ':disabled="null"', 'disabled="false"', 'disabled="0"', 'disabled=""', ':disabled="\'false\'"', ''] as $attribute) {
            $this->assertFalse($disabled($attribute), $attribute);
        }
    }

    /**
     * 商品詳細の「カートに入れる」は起動前の disabled で描画される（wire:loading で処理中に押せなくする指定は残る）
     */
    #[Test]
    public function 商品詳細のカートに入れるは起動前に押せない(): void
    {
        $item = $this->makeItem('じゃがいも', 2, 330);

        $html = $this->actingAs($this->makeUser())->get(route('shop.items.show', $item))->assertOk()->getContent();
        $tag = $this->tagWith((string) $html, 'wire:click="addToCart"');

        $this->assertStringContainsString('disabled="disabled"', $tag);
        $this->assertStringContainsString('x-bind:disabled="false"', $tag);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $tag);
    }

    /**
     * カートの数量の入力欄は起動前の readonly、「削除」は起動前の disabled で描画される
     */
    #[Test]
    public function カートの数量と削除は起動前に押せない(): void
    {
        $this->makeAreas();
        $item = $this->makeItem('じゃがいも', 2, 330);
        session(['cart' => [$item->id => 2]]);

        $html = (string) $this->actingAs($this->makeUser())->get(route('shop.cart'))->assertOk()->getContent();

        $input = $this->tagWith($html, 'wire:change="updateQuantity('.$item->id);
        $this->assertStringContainsString('readonly="readonly"', $input);
        $this->assertStringContainsString('x-bind:readonly="false"', $input);
        $this->assertStringNotContainsString('disabled="disabled"', $input);

        $remove = $this->tagWith($html, 'wire:click="remove('.$item->id);
        $this->assertStringContainsString('disabled="disabled"', $remove);
        $this->assertStringContainsString('x-bind:disabled="false"', $remove);
    }

    /**
     * 購入ページの数量は起動前の readonly で、値（name・value）は注文のフォームと一緒に送信される。「＋新しい住所を追加」は起動前の disabled。「住所を追加」（モーダルの中）は対象外
     */
    #[Test]
    public function 購入ページの数量と住所追加は起動前に押せない(): void
    {
        $this->makeAreas();
        $item = $this->makeItem('じゃがいも', 2, 330);
        session(['cart' => [$item->id => 2]]);

        $html = (string) $this->actingAs($this->makeUser())->get(route('shop.checkout.index'))->assertOk()->getContent();

        // 数量：disabled だと送信されず、起動前に「注文を確定する」を押すと数量が抜ける。readonly なら送信される
        $input = $this->tagWith($html, 'wire:change="updateQuantity('.$item->id);
        $this->assertStringContainsString('readonly="readonly"', $input);
        $this->assertStringContainsString('x-bind:readonly="false"', $input);
        $this->assertStringNotContainsString('disabled', $input);
        $this->assertStringContainsString('name="items[0][quantity]"', $input);
        $this->assertStringContainsString('value="2"', $input);

        $address = $this->tagWith($html, 'wire:click="openAddressModal"');
        $this->assertStringContainsString('disabled="disabled"', $address);
        $this->assertStringContainsString('x-bind:disabled="false"', $address);
        $this->assertStringNotContainsString('disabled="disabled"', $this->tagWith($html, 'wire:click="addAddress"'));
    }

    /**
     * 予約ページの数量は起動前の readonly で、値（name・value）は予約のフォームと一緒に送信される
     */
    #[Test]
    public function 予約ページの数量は起動前に押せない(): void
    {
        $this->makeAreas();
        $item = $this->makeReservedItem();

        $html = (string) $this->actingAs($this->makeUser())->get(route('shop.reservation.index', $item))->assertOk()->getContent();
        $tag = $this->tagWith($html, 'wire:change="updateQuantity($event.target.value)"');

        $this->assertStringContainsString('readonly="readonly"', $tag);
        $this->assertStringContainsString('x-bind:readonly="false"', $tag);
        $this->assertStringNotContainsString('disabled', $tag);
        $this->assertStringContainsString('name="quantity"', $tag);
    }

    /**
     * 購入ページのお届け先氏名・電話番号は起動前の readonly（disabled ではない）で、name と value が付き、注文のフォームと一緒に送信される
     */
    #[Test]
    public function 購入ページのお届け先氏名と電話番号は起動前にreadonlyで値が送信される(): void
    {
        $this->makeAreas();
        $item = $this->makeItem('じゃがいも', 2, 330);
        session(['cart' => [$item->id => 2]]);

        $html = (string) $this->actingAs($this->makeUser())
            ->withSession(['_old_input' => ['delivery_name' => '山田 花子', 'delivery_tell' => '09012345678']])
            ->get(route('shop.checkout.index'))->assertOk()->getContent();

        $this->assertDeliveryInputIsReadonly($this->tagWith($html, 'name="delivery_name"'), 'delivery_name', '山田 花子');
        $this->assertDeliveryInputIsReadonly($this->tagWith($html, 'name="delivery_tell"'), 'delivery_tell', '09012345678');
    }

    /**
     * 予約ページのお届け先氏名・電話番号も、起動前の readonly（disabled ではない）で、name と value が付く
     */
    #[Test]
    public function 予約ページのお届け先氏名と電話番号は起動前にreadonlyで値が送信される(): void
    {
        $this->makeAreas();
        $item = $this->makeReservedItem();

        $html = (string) $this->actingAs($this->makeUser())
            ->withSession(['_old_input' => ['delivery_name' => '山田 花子', 'delivery_tell' => '09012345678']])
            ->get(route('shop.reservation.index', $item))->assertOk()->getContent();

        $this->assertDeliveryInputIsReadonly($this->tagWith($html, 'name="delivery_name"'), 'delivery_name', '山田 花子');
        $this->assertDeliveryInputIsReadonly($this->tagWith($html, 'name="delivery_tell"'), 'delivery_tell', '09012345678');
    }

    /**
     * お届け先の入力欄が、起動前の readonly で、起動後に外す属性・見た目のクラス・name・value を持ち、disabled ではないことを確かめる
     */
    private function assertDeliveryInputIsReadonly(string $tag, string $name, string $value): void
    {
        $this->assertStringContainsString('readonly="readonly"', $tag);
        $this->assertStringContainsString('x-bind:readonly="false"', $tag);
        $this->assertStringContainsString(LivewireBoot::READONLY_CLASSES, $tag);
        $this->assertStringNotContainsString('disabled', $tag);
        $this->assertStringContainsString('name="'.$name.'"', $tag);
        $this->assertStringContainsString('value="'.$value.'"', $tag);
    }

    /**
     * 起動後の更新リクエスト（支払方法の切り替えなど）の描画では、お届け先氏名・電話番号に readonly を付け直さず、入力した値が残る
     */
    #[Test]
    public function 更新リクエストの描画ではお届け先氏名と電話番号にreadonlyを付けず入力した値が残る(): void
    {
        $this->makeAreas();
        $item = $this->makeItem('じゃがいも', 2, 330);
        session(['cart' => [$item->id => 2]]);

        $component = Livewire::actingAs($this->makeUser())->test(PurchaseForm::class)
            ->set('deliveryName', 'UAT起動後太郎')
            ->set('deliveryTell', '0312345678')
            ->set('paymentMethod', 'cod');

        $html = $component->html();
        $name = $this->tagWith($html, 'name="delivery_name"');
        $tell = $this->tagWith($html, 'name="delivery_tell"');

        foreach ([$name, $tell] as $tag) {
            $this->assertStringNotContainsString('readonly="readonly"', $tag);
            $this->assertStringContainsString('x-bind:readonly="false"', $tag);
        }
        $this->assertStringContainsString('value="UAT起動後太郎"', $name);
        $this->assertStringContainsString('value="0312345678"', $tell);
    }

    /**
     * マイページの「新しい住所を追加」・注文履歴の「詳細」・ページ送りは起動前の disabled で描画される。ページ送りの端はもともと押せない
     */
    #[Test]
    public function マイページの住所追加と詳細とページ送りは起動前に押せない(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 6; $i++) {
            Delivery_address::factory()->create(['user_id' => $user->id, 'delivery_prefecture' => '東京都', 'delivery_city' => "テスト市{$i}"]);
        }
        $this->makePurchase($user);

        $html = (string) $this->actingAs($user)->get(route('shop.mypage.index'))->assertOk()->getContent();

        foreach (['wire:click="openAddressModal"', 'wire:click="openDetail(', 'aria-label="次のページ"', 'aria-label="2ページ目"'] as $needle) {
            $tag = $this->tagWith($html, $needle);
            $this->assertStringContainsString('disabled="disabled"', $tag, $needle);
            $this->assertStringContainsString('x-bind:disabled="false"', $tag, $needle);
        }
        // 1ページ目の「前のページ」は、起動しても押せないまま
        $first = $this->tagWith($html, 'aria-label="前のページ"');
        $this->assertStringContainsString('disabled="disabled"', $first);
        $this->assertStringContainsString('x-bind:disabled="true"', $first);
    }

    /**
     * ページ送りの端（最後のページの「次のページ」）はもともと押せない。途中のページでは両方とも押せる条件
     */
    #[Test]
    public function ページ送りの端は押せない条件のまま(): void
    {
        $paginator = fn (int $page): LengthAwarePaginator => new LengthAwarePaginator(['a', 'b', 'c', 'd', 'e'], 15, 5, $page);
        $render = fn (int $page): string => view('livewire.partials.pager', ['paginator' => $paginator($page), 'pageName' => 'p'])->render();

        $last = $render(3);
        $this->assertStringContainsString('x-bind:disabled="true"', $this->tagWith($last, 'aria-label="次のページ"'));
        $this->assertStringContainsString('x-bind:disabled="false"', $this->tagWith($last, 'aria-label="前のページ"'));

        // 更新リクエストの描画：押せる条件には disabled を付けず、端には付ける
        request()->headers->set('X-Livewire', 'true');
        $middle = $render(2);
        $this->assertStringNotContainsString('disabled="disabled"', $this->tagWith($middle, 'aria-label="前のページ"'));
        $this->assertStringNotContainsString('disabled="disabled"', $this->tagWith($middle, 'aria-label="次のページ"'));
        $this->assertStringContainsString('disabled="disabled"', $this->tagWith($render(3), 'aria-label="次のページ"'));
    }

    /**
     * 購入ページで数量が範囲外のとき、その商品の欄だけ赤枠・aria-invalid になり、直すと消える
     */
    #[Test]
    public function 購入ページの数量のエラーはその商品の欄だけ赤枠になり直すと消える(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        session(['cart' => [$potato->id => 2, $onion->id => 1]]);

        $component = Livewire::actingAs($user)->test(PurchaseForm::class);
        $this->assertStringNotContainsString('aria-invalid', $component->html());

        $component->call('updateQuantity', $potato->id, '0');
        $first = $this->tagWith($component->html(), 'id="purchase-quantity-'.$potato->id.'"');
        $second = $this->tagWith($component->html(), 'id="purchase-quantity-'.$onion->id.'"');
        $this->assertStringContainsString('aria-invalid="true"', $first);
        $this->assertStringContainsString('border-danger', $first);
        $this->assertStringNotContainsString('aria-invalid', $second);
        $this->assertStringContainsString('border-line', $second);

        $component->call('updateQuantity', $potato->id, '2');
        $this->assertStringNotContainsString('aria-invalid', $component->html());
        $this->assertStringNotContainsString('border-danger', $this->tagWith($component->html(), 'id="purchase-quantity-'.$potato->id.'"'));
    }

    /**
     * 注文を確定してサーバー側の検証エラーで戻ったときも、その商品の欄が赤枠・aria-invalid になる
     */
    #[Test]
    public function サーバーの検証エラーで戻ったときも数量の欄が赤枠になる(): void
    {
        $this->makeAreas();
        $user = $this->makeUser();
        $potato = $this->makeItem('じゃがいも', 2, 330);
        $onion = $this->makeItem('玉ねぎ', 5, 795);
        session(['cart' => [$potato->id => 2, $onion->id => 1]]);

        $this->actingAs($user)
            ->from(route('shop.checkout.index'))
            ->post(route('shop.checkout.payment'), $this->paymentData($user, [$potato->id => 11, $onion->id => 1]))
            ->assertSessionHasErrors(['items.0.quantity']);

        $html = (string) $this->get(route('shop.checkout.index'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-invalid="true"', $this->tagWith($html, 'id="purchase-quantity-'.$potato->id.'"'));
        $this->assertStringNotContainsString('aria-invalid', $this->tagWith($html, 'id="purchase-quantity-'.$onion->id.'"'));
    }

    /**
     * 管理者ログインのフォームには二重送信を防ぐ属性があり、ボタンは送信中に押せなくなる（10秒たつと戻る）。送信の値（name/value）はボタンに頼らない
     */
    #[Test]
    public function 管理者ログインのフォームに二重送信防止の属性がある(): void
    {
        $html = (string) $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="{ sending: false }"', $html);
        $this->assertStringContainsString('if (sending) { $event.preventDefault(); return }; sending = true; setTimeout(() => sending = false, 10000)', $html);
        $this->assertStringContainsString('x-on:pageshow.window="sending = false"', $html);

        $this->assertSame(1, preg_match('#<button\b[^>]*>\s*ログイン\s*</button>#s', $html, $matches));
        $this->assertStringContainsString('x-bind:disabled="sending"', $matches[0]);
        $this->assertStringNotContainsString(' name=', $matches[0]);
        $this->assertStringNotContainsString(' value=', $matches[0]);
    }
}
