<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Support\Nav;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ヘッダーナビ（トップページ・静的ページ・ショップ）のテスト
 *
 * SP（768px未満）では、ヘッダーに出す項目と「メニュー」ボタンで開くパネルの項目に分かれる。
 * 見た目（表示・非表示）はブラウザのUATで確認し、ここでは HTML 上の振り分けを確認する。
 */
class HeaderNavTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ナビを確認するトップページ・静的ページのルート名
     *
     * @return array<string, array{string}>
     */
    public static function topNavRoutes(): array
    {
        return [
            'トップページ' => ['top.index'],
            '送料案内' => ['info.shipping-fee'],
            '利用規約' => ['info.terms-of-service'],
            'プライバシーポリシー' => ['info.privacy-policy'],
            '特定商取引法に基づく表記' => ['info.commercial-transaction'],
        ];
    }

    /**
     * ログイン中はマイページを表示し、ログアウトは表示しない
     */
    #[Test]
    #[DataProvider('topNavRoutes')]
    public function ログイン中はマイページが表示されログアウトは表示されない(string $routeName): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route($routeName));

        $response->assertOk();
        $response->assertSeeInOrder(['お知らせ', 'しおや農園について', 'オンラインショップ', 'マイページ']);
        $response->assertDontSee('ログアウト');
    }

    /**
     * 未ログインのときはマイページを表示しない
     */
    #[Test]
    #[DataProvider('topNavRoutes')]
    public function 未ログインのときはマイページが表示されない(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertSeeInOrder(['お知らせ', 'しおや農園について', 'オンラインショップ']);
        $response->assertDontSee('マイページ');
        $response->assertDontSee('ログアウト');
    }

    /**
     * ログイン中のトップ用ナビは未ログイン用に「マイページ」を足したものになる
     */
    #[Test]
    public function ログイン中のトップ用ナビは未ログイン用にマイページを足したものになる(): void
    {
        $guestLabels = collect(Nav::top())->pluck('label')->all();
        $authLabels = collect(Nav::topAuth())->pluck('label')->all();

        $this->assertSame([...$guestLabels, 'マイページ'], $authLabels);
    }

    /**
     * ナビの描画結果を「SPでヘッダーに出る項目」「SPのメニューに入る項目」「PCでヘッダーに並ぶ全項目」に分ける
     *
     * SPで出ない項目は、ヘッダー側では `hidden`（md: でのみ表示）のクラスが付く。
     *
     * @return array{bar: list<string>, menu: list<string>, pc: list<string>, menuForms: list<string>}
     */
    private function parseNav(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $clean = fn (string $text): string => preg_replace('/[\s＞]+/u', '', $text);

        $bar = [];
        $pc = [];
        foreach ($xpath->query('//header//nav/a | //header//nav/form') as $node) {
            $label = $clean($node->textContent);
            $pc[] = $label;
            if (! in_array('hidden', explode(' ', $node->getAttribute('class')), true)) {
                $bar[] = $label;
            }
        }

        $menu = [];
        foreach ($xpath->query('//nav[@id="sp-menu"]//li') as $node) {
            $menu[] = $clean($node->textContent);
        }

        $menuForms = [];
        foreach ($xpath->query('//nav[@id="sp-menu"]//form') as $node) {
            $menuForms[] = strtoupper($node->getAttribute('method')).' '.$node->getAttribute('action');
        }

        return compact('bar', 'menu', 'pc', 'menuForms');
    }

    /**
     * ヘッダーの項目とメニューの項目の振り分け（ルート・ログイン状態ごと）
     *
     * @return array<string, array{string, bool, list<string>, list<string>, list<string>}>
     */
    public static function spNavCases(): array
    {
        $about = ['お知らせ', 'しおや農園について'];
        $cases = [];

        foreach (self::topNavRoutes() as $name => [$routeName]) {
            $cases["{$name}（未ログイン）"] = [$routeName, false, ['オンラインショップ'], $about, [...$about, 'オンラインショップ']];
            $cases["{$name}（ログイン中）"] = [$routeName, true, ['オンラインショップ', 'マイページ'], $about, [...$about, 'オンラインショップ', 'マイページ']];
        }

        $cases['ショップ（未ログイン）'] = ['shop.items.index', false, ['ログイン'], $about, [...$about, 'ログイン']];
        $cases['ショップ（ログイン中）'] = ['shop.items.index', true, ['カート', 'マイページ'], [...$about, 'ログアウト'], [...$about, 'カート', 'マイページ', 'ログアウト']];
        $cases['カート'] = ['shop.cart', true, ['マイページ'], [...$about, 'ログアウト'], [...$about, 'マイページ', 'ログアウト']];

        return $cases;
    }

    /**
     * SPではよく使う項目だけをヘッダーに出し、残りをメニューに入れる。PCは今までどおり全項目を並べる
     *
     * @param  list<string>  $bar
     * @param  list<string>  $menu
     * @param  list<string>  $pc
     */
    #[Test]
    #[DataProvider('spNavCases')]
    public function スマホではよく使う項目がヘッダーに残りはメニューに入りパソコンは全項目が並ぶ(string $routeName, bool $loggedIn, array $bar, array $menu, array $pc): void
    {
        if ($loggedIn) {
            $this->actingAs(User::factory()->create());
        }

        $response = $this->get(route($routeName));

        $response->assertOk();
        $nav = $this->parseNav($response->getContent());
        $this->assertSame($bar, $nav['bar']);
        $this->assertSame($menu, $nav['menu']);
        $this->assertSame($pc, $nav['pc']);
    }

    /**
     * 予約ページ（shop.reservation.*）のヘッダーは、カート・購入手続きと同じナビ（マイページ・ログアウト）になる
     */
    #[Test]
    public function 予約のルートはカート用のナビになる(): void
    {
        $about = ['お知らせ', 'しおや農園について'];
        $item = Item::factory()->create(['is_reserved_only' => true, 'reservation_limit' => 200]);
        $this->actingAs(User::factory()->create());

        $cart = $this->parseNav($this->get(route('shop.cart'))->getContent());

        $page = $this->get(route('shop.reservation.index', $item));
        $page->assertOk();
        $nav = $this->parseNav($page->getContent());
        $this->assertSame(['マイページ'], $nav['bar']);
        $this->assertSame([...$about, 'ログアウト'], $nav['menu']);
        $this->assertSame([...$about, 'マイページ', 'ログアウト'], $nav['pc']);
        $this->assertSame($cart, $nav);
    }

    /**
     * ログアウトはメニューの中に、POSTのフォーム（CSRFトークン付き）として置く
     */
    #[Test]
    public function ログアウトはメニューの中に送信フォームで置かれる(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('shop.items.index'));

        $nav = $this->parseNav($response->getContent());
        $this->assertSame(['POST '.route('logout')], $nav['menuForms']);
        $this->assertNotContains('ログアウト', $nav['bar']);
        $response->assertSee('name="_token"', false);
    }

    /**
     * ログアウトのフォームが無い（未ログイン・トップ用）メニューにはフォームが無い
     */
    #[Test]
    public function ログイン中のトップ用メニューにはログアウトのフォームが無い(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('top.index'));

        $this->assertSame([], $this->parseNav($response->getContent())['menuForms']);
    }

    /**
     * メニューボタンは開閉の状態（aria-expanded）とメニューのid（aria-controls）を持ち、初期状態は閉じている
     */
    #[Test]
    public function メニューボタンは開閉の状態と開くメニューを示す属性を持つ(): void
    {
        $response = $this->get(route('top.index'));

        $response->assertSee('aria-controls="sp-menu"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('id="sp-menu"', false);
        $response->assertSee('メニュー');
        $response->assertSee('閉じる');
    }

    /**
     * メニューの開閉に使う Alpine.js を、Livewire のスクリプトとして全ページで読み込む
     */
    #[Test]
    public function メニューの開閉用スクリプトとスタイルをレイアウトで読み込む(): void
    {
        $response = $this->get(route('top.index'));

        // APP_DEBUG によって livewire.js / livewire.min.js のどちらかになるため、パスの共通部分で確認する
        $this->assertMatchesRegularExpression('#<script src="[^"]*/livewire/livewire(\.min)?\.js#', $response->getContent());
        $response->assertSee('x-cloak', false);
    }
}
