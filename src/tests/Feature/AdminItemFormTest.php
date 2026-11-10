<?php

namespace Tests\Feature;

use App\Enums\CartAddResult;
use App\Http\Controllers\AdminItemController;
use App\Http\Requests\ItemStoreRequest;
use App\Models\Admin;
use App\Models\Item;
use App\Models\Purchase_history;
use App\Models\Purchase_history_item;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Services\AdminItemService;
use App\Services\CartService;
use App\Services\ItemInput;
use App\Services\ProductImage\GdProductImageStorage;
use App\Services\ProductImage\PreparedImage;
use App\Services\ProductImage\ProductImageStorageInterface;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReservationTestHelpers;
use Tests\TestCase;

/**
 * 管理画面の掲載商品の登録・修正（admin.items.create・store・edit・update）のテスト
 */
class AdminItemFormTest extends TestCase
{
    use RefreshDatabase;
    use ReservationTestHelpers;

    /** 1x1 の webp 画像（GD が webp に対応していなくてもアップロードを作れるように、バイト列で持つ） */
    private const string WEBP_BASE64 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * 管理者としてログインする
     */
    private function loginAsAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /**
     * 通常の商品を作る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeListed(string $name = 'じゃがいも', int $weight = 2, array $overrides = []): Item
    {
        return Item::factory()->create(['name' => $name, 'weight' => $weight, 'price' => 330, 'is_reserved_only' => false, 'reservation_limit' => null, 'recommend' => false, ...$overrides]);
    }

    /**
     * 登録・修正に送る正しい入力（通常の商品）
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return [
            'name' => 'UAT登録通常',
            'weight' => '2',
            'price' => '1500',
            'image' => UploadedFile::fake()->image('photo.png', 20, 20),
            'detail' => 'UATの紹介文です。',
            'recommend' => '1',
            'is_reserved_only' => '0',
            ...$overrides,
        ];
    }

    /**
     * 登録ページから登録を送る
     *
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = []): TestResponse
    {
        return $this->from(route('admin.items.create'))->post(route('admin.items.store'), $this->input($overrides));
    }

    /**
     * 修正ページから修正を送る（既定は、画像を選ばない・ほかは正しい入力）
     *
     * @param  array<string, mixed>  $overrides
     */
    private function update(Item $item, array $overrides = []): TestResponse
    {
        return $this->from(route('admin.items.edit', $item->id))->post(route('admin.items.update'), $this->input(['id' => (string) $item->id, 'image' => null, ...$overrides]));
    }

    /**
     * webp の画像のアップロードを作る
     */
    private function webp(string $name = 'photo.webp'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, (string) base64_decode(self::WEBP_BASE64, true));
    }

    /**
     * 保存された商品画像のファイル（public ディスクの image/product/uploads/ の中）
     *
     * @return list<string>
     */
    private function storedImages(): array
    {
        return Storage::disk('public')->allFiles(ProductImageStorageInterface::DIRECTORY);
    }

    /**
     * 4つのルートは決められたコントローラーが受け、管理者の認証と no-store が付く。修正のIDは数字だけ
     */
    #[Test]
    public function ルートは決められたコントローラーが受け管理者の認証とno_storeを要する(): void
    {
        $routes = [
            'admin.items.create' => [AdminItemController::class.'@create', 'GET', '/admin/items/create'],
            'admin.items.store' => [AdminItemController::class.'@store', 'POST', '/admin/items/store'],
            'admin.items.edit' => [AdminItemController::class.'@edit', 'GET', '/admin/items/edit/{item_id}'],
            'admin.items.update' => [AdminItemController::class.'@update', 'POST', '/admin/items/update'],
        ];

        foreach ($routes as $name => [$action, $method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame($action, $route->getActionName(), $name);
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, '/'.$route->uri(), $name);
            $this->assertContains('auth:admin', $route->gatherMiddleware(), $name);
            $this->assertContains('no-store', $route->gatherMiddleware(), $name);
        }
        $this->assertSame('[0-9]+', Route::getRoutes()->getByName('admin.items.edit')->wheres['item_id']);
    }

    /**
     * AC-1：未ログインでは、登録・修正のページも、登録・更新のリクエストも、ログインページへ移動する（何も変わらない）
     */
    #[Test]
    public function 未ログインではログインページへ移動し何も変わらない(): void
    {
        $item = $this->makeListed();

        $this->get(route('admin.items.create'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.items.edit', $item->id))->assertRedirect(route('admin.login'));
        $this->post(route('admin.items.store'), $this->input())->assertRedirect(route('admin.login'));
        $this->post(route('admin.items.update'), $this->input(['id' => (string) $item->id, 'name' => '変えた名前']))->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('items', 1);
        $this->assertSame('じゃがいも', $item->fresh()->name);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * AC-1：会員としてログインしていても、管理者としては未ログインのため入れない
     */
    #[Test]
    public function 会員としてログインしていても入れない(): void
    {
        $item = $this->makeListed();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.items.create'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.items.edit', $item->id))->assertRedirect(route('admin.login'));
        $this->post(route('admin.items.store'), $this->input())->assertRedirect(route('admin.login'));
        $this->post(route('admin.items.update'), $this->input(['id' => (string) $item->id]))->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('items', 1);
    }

    /**
     * AC-2・AC-3：登録ページにタイトル・各項目・プレースホルダー・プレビューの「未設定」・登録ボタンが出て、ラジオはどれも選ばれていない。no-store が付く
     */
    #[Test]
    public function 登録ページに項目が出てラジオは未選択(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.items.create'))
            ->assertOk()
            ->assertViewIs('admin.item_create')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('<h1 class="text-center text-[28px] font-normal text-primary-text lg:text-[32px]">掲載商品登録</h1>', false)
            ->assertSee('action="'.route('admin.items.store').'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSeeInOrder(['商品名', '商品重量', '商品金額', '商品画像', 'プレビュー', '商品紹介文', 'おすすめ商品', '予約限定', '予約可能数'])
            ->assertSee('placeholder="例：じゃがいも"', false)
            ->assertSee('placeholder="例：330"', false)
            ->assertSee('placeholder="例：ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです"', false)
            ->assertSee('placeholder="例：200"', false)
            ->assertSeeInOrder(['2kg', '5kg', '10kg'])
            ->assertSee('円(税込)')
            ->assertSee('画像を選択')
            ->assertSee('ここに画像をドラッグ＆ドロップ または')
            ->assertSee('未設定')
            ->assertSee('はい')
            ->assertSee('いいえ')
            ->assertSee('限定')
            ->assertSee('通常')
            ->assertSee('上限なし')
            ->assertSee('上限あり')
            ->assertSee('個')
            ->assertSee('登録する')
            ->assertSee('一覧へ戻る')
            ->assertSee('href="'.route('admin.items.index').'"', false)
            ->assertSee('予約限定で「限定」を選ぶと設定できます')
            ->assertSee('通常の商品は予約を受け付けません')
            ->assertDontSee('Figma')
            ->assertDontSee('㎏')
            ->assertDontSee('商品価格')
            ->assertDontSee('商品詳細');
        $html = $response->getContent();

        // 7つの項目のラジオ（重さ3・おすすめ2・予約限定2・上限2）は、どれも checked が付かない
        $this->assertSame(9, preg_match_all('/<input[^>]*type="radio"/', $html));
        $this->assertSame(0, preg_match_all('/<input[^>]*type="radio"[^>]*\schecked(?![:\w-])/', $html));
        // 予約可能数の部品は、最初は無効（グレー）
        $this->assertMatchesRegularExpression('/<fieldset[^>]*\sdisabled(?![:\w-])/', $html);
        // 予約可能数の数の欄も最初は無効
        $this->assertMatchesRegularExpression('/<input[^>]*id="reservation_limit"[^>]*\sdisabled(?![:\w-])/', $html);
        // 修正用の hidden の id は出ない
        $this->assertStringNotContainsString('name="id"', $html);
        // 画像は jpeg・png・webp だけ選べる
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
    }

    /**
     * 登録ページのサイドメニューでは「掲載商品登録」が現在のページ（太字）になる。メニューとトップのリンクはルート名で作る
     */
    #[Test]
    public function 登録ページではメニューの掲載商品登録が現在のページになる(): void
    {
        $this->loginAsAdmin();

        $html = $this->get(route('admin.items.create'))->getContent();

        $this->assertMatchesRegularExpression('#<a [^>]*href="'.preg_quote(route('admin.items.create'), '#').'"[^>]*aria-current="page"[^>]*>|<a [^>]*aria-current="page"[^>]*href="'.preg_quote(route('admin.items.create'), '#').'"[^>]*>#', $html);
        $links = collect(AdminNav::groups())->flatMap(fn (array $group): array => $group['links'])->pluck('url', 'label');
        $this->assertSame(route('admin.items.create'), $links['掲載商品登録']);
        $this->assertSame(route('admin.items.index'), $links['掲載商品一覧']);
    }

    /**
     * AC-5・AC-6：正しく入力して登録すると、商品が保存され（画像は public の image/product/uploads/ に縮小してハッシュ名で）、一覧に戻って緑の案内が出る。公開側にも出る
     */
    #[Test]
    public function 登録すると商品が保存され一覧に戻る(): void
    {
        $this->loginAsAdmin();

        $this->store()
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('list_status', '商品を登録しました。')
            ->assertSessionHasNoErrors();

        $item = Item::sole();
        $this->assertSame('UAT登録通常', $item->name);
        $this->assertSame(2, $item->weight);
        $this->assertSame(1500, $item->price);
        $this->assertSame('UATの紹介文です。', $item->detail);
        $this->assertTrue((bool) $item->recommend);
        $this->assertFalse((bool) $item->is_reserved_only);
        $this->assertNull($item->reservation_limit);
        $this->assertMatchesRegularExpression('#^image/product/uploads/[A-Za-z0-9]{40}\.(webp|png|jpg)$#', $item->img_url);
        Storage::disk('public')->assertExists($item->img_url);

        // 一覧に緑の案内と商品が出る（元の重さの並び：商品名のまとまり・重さの順）
        $this->get(route('admin.items.index'))
            ->assertOk()
            ->assertSee('商品を登録しました。')
            ->assertSee('UAT登録通常');
        // 公開側の商品一覧・商品詳細に出る
        Auth::shouldUse('web');
        $this->get(route('shop.items.index'))->assertOk()->assertSee('UAT登録通常');
        $this->get(route('shop.items.show', $item->id))->assertOk()->assertSee('UAT登録通常');
    }

    /**
     * AC-10：予約可能数は、限定・上限なしは null、限定・上限ありは数、通常は null で保存される。詳細モーダルの表記もそれに合う
     */
    #[Test]
    public function 予約可能数は限定と通常で保存する値が変わる(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '限定上限なし', 'is_reserved_only' => '1', 'reservation_limit_type' => 'unlimited', 'reservation_limit' => '50'])->assertSessionHasNoErrors();
        $this->store(['name' => '限定上限あり', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '200'])->assertSessionHasNoErrors();
        // 通常のときは、送られてきた予約可能数を捨てる（無効なら送られないが、改変された送信も防ぐ）
        $this->store(['name' => '通常', 'is_reserved_only' => '0', 'reservation_limit_type' => 'limited', 'reservation_limit' => '300'])->assertSessionHasNoErrors();
        // 通常のときは、予約可能数の入力が正しくなくても検証しない
        $this->store(['name' => '通常その2', 'is_reserved_only' => '0', 'reservation_limit_type' => 'zzz', 'reservation_limit' => 'abc'])->assertSessionHasNoErrors();

        $unlimited = Item::where('name', '限定上限なし')->sole();
        $limited = Item::where('name', '限定上限あり')->sole();
        $normal = Item::where('name', '通常')->sole();
        $this->assertTrue((bool) $unlimited->is_reserved_only);
        $this->assertNull($unlimited->reservation_limit);
        $this->assertTrue((bool) $limited->is_reserved_only);
        $this->assertSame(200, $limited->reservation_limit);
        $this->assertFalse((bool) $normal->is_reserved_only);
        $this->assertNull($normal->reservation_limit);
        $this->assertNull(Item::where('name', '通常その2')->sole()->reservation_limit);

        $service = app(AdminItemService::class);
        $this->assertSame('上限なし', $service->findDetail($unlimited->id)?->reservationLimitLabel());
        $this->assertSame('200（残り200）', $service->findDetail($limited->id)?->reservationLimitLabel());
        $this->assertSame('無', $service->findDetail($normal->id)?->reservationLimitLabel());
    }

    /**
     * AC-6：登録した予約限定の商品は予約ページが開き、おすすめ「はい」の商品はおすすめの候補になる
     */
    #[Test]
    public function 登録した予約限定の商品は予約ページが開く(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '限定の商品', 'weight' => '5', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '10'])->assertSessionHasNoErrors();
        $item = Item::sole();
        $this->assertTrue((bool) $item->recommend);

        Auth::shouldUse('web');
        $this->actingAs(User::factory()->create())->get(route('shop.reservation.index', $item->id))->assertOk()->assertSee('限定の商品');
    }

    /**
     * AC-7：何も入力せずに登録すると、登録されず、項目ごとにエラーが出る（画像以外の入力は元の画面に戻す）
     */
    #[Test]
    public function 何も入力しないと項目ごとにエラーになる(): void
    {
        $this->loginAsAdmin();

        $this->post(route('admin.items.store'))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'name' => '商品名を入力してください',
                'weight' => '商品重量を選択してください',
                'price' => '商品金額を入力してください',
                'image' => '商品画像を選択してください',
                'detail' => '商品紹介文を入力してください',
                'recommend' => 'おすすめ商品を選択してください',
                'is_reserved_only' => '予約限定を選択してください',
            ]);

        $this->assertDatabaseCount('items', 0);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * AC-7：境界値と形式の誤り（商品名255/256文字・金額0/1/100000/100001/12.5・紹介文1000/1001文字・重さ・おすすめ・予約限定の不正）
     */
    #[Test]
    public function 入力の境界値と誤りを検証する(): void
    {
        $this->loginAsAdmin();

        $invalid = [
            '商品名256文字' => [['name' => str_repeat('あ', 256)], 'name', '商品名は255文字以内で入力してください'],
            '商品名が配列' => [['name' => ['a']], 'name', '商品名は文字で入力してください'],
            '金額0' => [['price' => '0'], 'price', '商品金額は1円以上で入力してください'],
            '金額マイナス' => [['price' => '-1'], 'price', '商品金額は1円以上で入力してください'],
            '金額100001' => [['price' => '100001'], 'price', '商品金額は100,000円以下で入力してください'],
            '金額が小数' => [['price' => '12.5'], 'price', '商品金額は整数で入力してください'],
            '金額が文字' => [['price' => 'abc'], 'price', '商品金額は整数で入力してください'],
            '紹介文1001文字' => [['detail' => str_repeat('あ', 1001)], 'detail', '商品紹介文は1,000文字以内で入力してください'],
            '重さが2・5・10以外' => [['weight' => '3'], 'weight', '商品重量を選択してください'],
            'おすすめが不正' => [['recommend' => 'yes'], 'recommend', 'おすすめ商品を選択してください'],
            '予約限定が不正' => [['is_reserved_only' => 'maybe'], 'is_reserved_only', '予約限定を選択してください'],
            '予約限定が未選択' => [['is_reserved_only' => null], 'is_reserved_only', '予約限定を選択してください'],
        ];
        foreach ($invalid as $case => [$overrides, $field, $message]) {
            $this->store($overrides)
                ->assertRedirect(route('admin.items.create'))
                ->assertSessionHasErrors([$field => $message]);
            $this->assertSame(0, Item::count(), $case);
        }
        $this->assertSame([], $this->storedImages());

        // 境界値は登録できる
        $this->store(['name' => str_repeat('あ', 255), 'price' => '1', 'detail' => str_repeat('あ', 1000)])->assertSessionHasNoErrors();
        $this->store(['name' => '高い商品', 'price' => '100000'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('items', 2);
    }

    /**
     * AC-7：エラーで戻ったとき、画像以外の入力が old() で残り、ラジオの選択も残る
     */
    #[Test]
    public function エラーで戻ると入力とラジオの選択が残る(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '残る名前', 'weight' => '5', 'price' => '0', 'detail' => '残る紹介文', 'recommend' => '0', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '12'])
            ->assertSessionHasErrors('price');

        $html = $this->get(route('admin.items.create'))->assertOk()->getContent();

        $this->assertStringContainsString('value="残る名前"', $html);
        $this->assertStringContainsString('value="0"', $html);
        $this->assertStringContainsString('>残る紹介文</textarea>', $html);
        $this->assertStringContainsString('商品金額は1円以上で入力してください', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="weight"[^>]*value="5"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="recommend"[^>]*value="0"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="is_reserved_only"[^>]*value="1"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="reservation_limit_type"[^>]*value="limited"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertStringContainsString('value="12"', $html);
        // 限定なので予約可能数の部品は有効
        $this->assertDoesNotMatchRegularExpression('/<fieldset[^>]*\sdisabled(?![:\w-])/', $html);
    }

    /**
     * AC-7：商品画像の形式（jpeg・png・webp）と大きさ（10MB＝10240KB以下）
     */
    #[Test]
    public function 商品画像の形式と大きさを検証する(): void
    {
        $this->loginAsAdmin();

        $this->store(['image' => UploadedFile::fake()->image('photo.gif', 20, 20)])->assertSessionHasErrors(['image' => '商品画像はjpeg・png・webpのいずれかの形式にしてください']);
        $this->store(['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors(['image' => '商品画像はjpeg・png・webpのいずれかの形式にしてください']);
        $this->store(['image' => UploadedFile::fake()->image('big.png', 20, 20)->size(10241)])->assertSessionHasErrors(['image' => '商品画像は10MB以下にしてください']);
        $this->assertDatabaseCount('items', 0);
        $this->assertSame([], $this->storedImages());

        // jpeg・ちょうど10MBは登録できる（2MBを超える画像も登録できる）
        $this->store(['name' => 'jpeg', 'image' => UploadedFile::fake()->image('photo.jpg', 20, 20)])->assertSessionHasNoErrors();
        $this->store(['name' => '10MB', 'image' => UploadedFile::fake()->image('ten.png', 20, 20)->size(10240)])->assertSessionHasNoErrors();
        $this->store(['name' => '3MB', 'image' => UploadedFile::fake()->image('three.png', 20, 20)->size(3000)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('items', 3);
        $this->assertCount(3, $this->storedImages());
    }

    /**
     * webp の画像も登録できる（GD が webp の読み込み・書き出しに対応している環境だけ）
     */
    #[Test]
    public function webpの画像を登録するとwebpで保存される(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('この環境の GD は webp に対応していません。');
        }
        $this->loginAsAdmin();

        $this->store(['name' => 'webp', 'image' => $this->webp()])->assertSessionHasNoErrors();

        $this->assertMatchesRegularExpression('#^image/product/uploads/[A-Za-z0-9]{40}\.webp$#', Item::where('name', 'webp')->sole()->img_url);
    }

    /**
     * 長辺が1200pxを超える画像は縦横比を保って縮小して保存し、小さい画像は拡大しない
     */
    #[Test]
    public function 大きい画像は長辺1200pxに縮小し小さい画像は拡大しない(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '横長', 'image' => UploadedFile::fake()->image('wide.jpg', 3000, 1500)])->assertSessionHasNoErrors();
        $this->store(['name' => '縦長', 'image' => UploadedFile::fake()->image('tall.png', 900, 2400)])->assertSessionHasNoErrors();
        $this->store(['name' => '小さい', 'image' => UploadedFile::fake()->image('small.png', 300, 200)])->assertSessionHasNoErrors();
        $this->store(['name' => 'ちょうど', 'image' => UploadedFile::fake()->image('edge.png', 1200, 800)])->assertSessionHasNoErrors();

        $size = fn (string $name): array => array_slice((array) getimagesizefromstring(Storage::disk('public')->get(Item::where('name', $name)->sole()->img_url)), 0, 2);
        $this->assertSame([1200, 600], $size('横長'));
        $this->assertSame([450, 1200], $size('縦長'));
        $this->assertSame([300, 200], $size('小さい'));
        $this->assertSame([1200, 800], $size('ちょうど'));
    }

    /**
     * 形式は jpeg・png・webp に見えても、画像として読み込めないファイルは画像の欄のエラーにする（登録されず、ファイルも残らない）
     */
    #[Test]
    public function 読み込めない画像は画像の欄のエラーになる(): void
    {
        $this->loginAsAdmin();
        $broken = UploadedFile::fake()->createWithContent('broken.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 20));

        $this->store(['image' => $broken])->assertSessionHasErrors(['image' => GdProductImageStorage::UNREADABLE_MESSAGE]);

        $this->assertDatabaseCount('items', 0);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * PHPのアップロードの上限を超えて受け取れなかった画像も、画像の欄のエラーにする（登録されない）
     */
    #[Test]
    public function アップロードに失敗した画像は画像の欄のエラーになる(): void
    {
        $this->loginAsAdmin();
        $path = tempnam(sys_get_temp_dir(), 'img');
        $failed = new UploadedFile((string) $path, 'big.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        $response = $this->store(['image' => $failed]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseCount('items', 0);
        @unlink((string) $path);
    }

    /**
     * AC-8：削除していない同じ商品名・重さがあると登録できない。削除済みの商品や、重さが違う組み合わせは登録できる。画像は残らない
     */
    #[Test]
    public function 同じ商品名と重さは登録できない(): void
    {
        $this->loginAsAdmin();
        $this->makeListed('じゃがいも', 2);
        $this->makeListed('削除済み', 2)->delete();

        $this->store(['name' => 'じゃがいも', 'weight' => '2'])
            ->assertRedirect(route('admin.items.create'))
            ->assertSessionHasErrors(['name' => 'じゃがいも 2kg はすでに登録されています']);
        $this->assertDatabaseCount('items', 2);
        $this->assertSame([], $this->storedImages());

        $this->store(['name' => 'じゃがいも', 'weight' => '5'])->assertSessionHasNoErrors();
        $this->store(['name' => '削除済み', 'weight' => '2'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('items', 4);
    }

    /**
     * AC-11：限定・上限ありで、数が空・0以下・整数でないとエラー。上限の有無が未選択でもエラー（限定のとき）
     */
    #[Test]
    public function 上限ありの数が正しくないとエラーになる(): void
    {
        $this->loginAsAdmin();
        $base = ['is_reserved_only' => '1', 'reservation_limit_type' => 'limited'];

        $this->store($base)->assertSessionHasErrors(['reservation_limit' => '予約可能数を入力してください']);
        $this->store($base + ['reservation_limit' => ''])->assertSessionHasErrors(['reservation_limit' => '予約可能数を入力してください']);
        $this->store($base + ['reservation_limit' => '0'])->assertSessionHasErrors(['reservation_limit' => '予約可能数は1以上で入力してください']);
        $this->store($base + ['reservation_limit' => '-1'])->assertSessionHasErrors(['reservation_limit' => '予約可能数は1以上で入力してください']);
        $this->store($base + ['reservation_limit' => '3.5'])->assertSessionHasErrors(['reservation_limit' => '予約可能数は整数で入力してください']);
        $this->store($base + ['reservation_limit' => '1000'])->assertSessionHasErrors(['reservation_limit' => '予約可能数は999以下で入力してください']);
        $this->store($base + ['reservation_limit' => '100000'])->assertSessionHasErrors(['reservation_limit' => '予約可能数は999以下で入力してください']);
        $this->store(['is_reserved_only' => '1'])->assertSessionHasErrors(['reservation_limit_type' => '予約可能数の上限の有無を選択してください']);
        $this->store(['is_reserved_only' => '1', 'reservation_limit_type' => 'many'])->assertSessionHasErrors(['reservation_limit_type' => '予約可能数の上限の有無を選択してください']);
        // 上限なしなら、数は検証しない
        $this->store(['is_reserved_only' => '1', 'reservation_limit_type' => 'unlimited', 'reservation_limit' => 'abc'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('items', 1);
        $this->assertNull(Item::sole()->reservation_limit);
        $this->store($base + ['name' => '1個', 'reservation_limit' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, Item::where('name', '1個')->sole()->reservation_limit);
        $this->store($base + ['name' => '999個', 'reservation_limit' => '999'])->assertSessionHasNoErrors();
        $this->assertSame(999, Item::where('name', '999個')->sole()->reservation_limit);
    }

    /**
     * AC-20：同じ内容を続けて送っても1件しか登録されない（2回目は重複で登録されず、画像も残らない）
     */
    #[Test]
    public function 同じ内容を続けて送っても1件だけ登録される(): void
    {
        $this->loginAsAdmin();

        $this->store()->assertRedirect(route('admin.items.index'));
        $this->store()->assertSessionHasErrors('name');

        $this->assertDatabaseCount('items', 1);
        $this->assertCount(1, $this->storedImages());
    }

    /**
     * AC-20：送信ボタンは、送信中は押せなくなる（Alpine の sending）。ページが戻ってきたときは元に戻す
     */
    #[Test]
    public function 送信中はボタンを押せなくして二重送信を防ぐ(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed();

        foreach ([route('admin.items.create'), route('admin.items.edit', $item->id)] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringContainsString('x-on:submit="if (sending) { $event.preventDefault(); return }; sending = true"', $html);
            $this->assertStringContainsString('x-on:pageshow.window="sending = false"', $html);
            $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*x-bind:disabled="sending"/', $html);
        }
    }

    /**
     * AC-12：修正ページに、青いタイトルと、今の値が入った入力欄・選択済みのラジオ・今の画像のプレビューが出る。メニューは「掲載商品一覧」が現在のページ
     */
    #[Test]
    public function 修正ページに今の値が入る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('北の恵みセット', 5, ['price' => 2980, 'detail' => '北の恵みの詰め合わせです。', 'img_url' => 'image/product/potato-card.webp', 'recommend' => false, 'is_reserved_only' => true, 'reservation_limit' => 300]);

        $html = $this->get(route('admin.items.edit', $item->id))
            ->assertOk()
            ->assertViewIs('admin.item_edit')
            ->assertViewHas('item', fn (Item $shown): bool => $shown->is($item))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('<h1 class="text-center text-[28px] font-normal text-mail-link lg:text-[32px]">掲載商品修正</h1>', false)
            ->assertSee('action="'.route('admin.items.update').'"', false)
            ->assertSee('<input type="hidden" name="id" value="'.$item->id.'">', false)
            ->assertSee('value="北の恵みセット"', false)
            ->assertSee('value="2980"', false)
            ->assertSee('>北の恵みの詰め合わせです。</textarea>', false)
            ->assertSee('src="'.asset('storage/image/product/potato-card.webp').'"', false)
            ->assertSee('修正する')
            ->assertSee('一覧へ戻る')
            ->assertSee('href="'.route('admin.items.index').'"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="weight"[^>]*value="5"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="weight"[^>]*value="2"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="recommend"[^>]*value="0"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="is_reserved_only"[^>]*value="1"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="reservation_limit_type"[^>]*value="limited"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="reservation_limit"[^>]*value="300"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<fieldset[^>]*\sdisabled(?![:\w-])/', $html);
        // サイドメニューは「掲載商品一覧」が現在のページ
        $this->assertMatchesRegularExpression('#<a [^>]*href="'.preg_quote(route('admin.items.index'), '#').'"[^>]*aria-current="page"[^>]*>|<a [^>]*aria-current="page"[^>]*href="'.preg_quote(route('admin.items.index'), '#').'"[^>]*>#', $html);
    }

    /**
     * 修正ページの初期表示：通常の商品は予約可能数が無効、限定・上限なしは「上限なし」が選ばれ数の欄は無効
     */
    #[Test]
    public function 修正ページの予約可能数の初期表示は商品の種類で変わる(): void
    {
        $this->loginAsAdmin();
        $normal = $this->makeListed('通常の商品', 2);
        $unlimited = $this->makeListed('上限なしの商品', 2, ['is_reserved_only' => true, 'reservation_limit' => null]);

        $html = $this->get(route('admin.items.edit', $normal->id))->getContent();
        $this->assertMatchesRegularExpression('/<fieldset[^>]*\sdisabled(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="is_reserved_only"[^>]*value="0"[^>]*\schecked(?![:\w-])/', $html);

        $html = $this->get(route('admin.items.edit', $unlimited->id))->getContent();
        $this->assertDoesNotMatchRegularExpression('/<fieldset[^>]*\sdisabled(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="reservation_limit_type"[^>]*value="unlimited"[^>]*\schecked(?![:\w-])/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="reservation_limit"[^>]*\sdisabled(?![:\w-])/', $html);
    }

    /**
     * AC-18：削除済み・存在しないIDの修正ページは、一覧に戻って赤字で「商品が見つかりません。」。数字でないIDは404
     */
    #[Test]
    public function 削除済みや存在しない商品の修正ページは一覧に戻る(): void
    {
        $this->loginAsAdmin();
        $gone = $this->makeListed('削除済み', 2);
        $gone->delete();

        foreach ([$gone->id, 99999, '99999999999999999999'] as $id) {
            $this->get(route('admin.items.edit', $id))
                ->assertRedirect(route('admin.items.index'))
                ->assertSessionHasErrors(['item' => '商品が見つかりません。']);
        }
        $this->followingRedirects()->get(route('admin.items.edit', 99999))->assertSee('商品が見つかりません。');
        $this->get(url('/admin/items/edit/abc'))->assertNotFound();
    }

    /**
     * AC-13・AC-14：値を変えて、画像を選ばずに修正すると、画像は今のまま・ほかは新しい値になり、一覧に戻って緑の案内が出る
     */
    #[Test]
    public function 画像を選ばずに修正すると画像は今のままになる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('UAT登録通常', 2, ['price' => 1500, 'img_url' => 'image/product/potato-card.webp']);

        $this->update($item, ['price' => '1800', 'detail' => '変えた紹介文', 'recommend' => '0'])
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHas('list_status', '商品を修正しました。')
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(1800, $item->price);
        $this->assertSame('変えた紹介文', $item->detail);
        $this->assertFalse((bool) $item->recommend);
        $this->assertSame('image/product/potato-card.webp', $item->img_url);
        $this->assertSame([], $this->storedImages());
        $this->assertDatabaseCount('items', 1);

        $this->get(route('admin.items.index'))->assertSee('商品を修正しました。')->assertSee('1,800');
        Auth::shouldUse('web');
        $this->get(route('shop.items.show', $item->id))->assertOk()->assertSee('1,800')->assertSee('変えた紹介文');
    }

    /**
     * AC-14：新しい画像を選ぶと差し替わる。古い画像が uploads の中のファイルなら消す
     */
    #[Test]
    public function 新しい画像を選ぶと差し替わりuploadsの古いファイルは消える(): void
    {
        $this->loginAsAdmin();
        $old = ProductImageStorageInterface::DIRECTORY.'/old.webp';
        Storage::disk('public')->put($old, 'old');
        $item = $this->makeListed('商品', 2, ['img_url' => $old]);

        $this->update($item, ['image' => UploadedFile::fake()->image('new.png', 20, 20)])->assertSessionHas('list_status', '商品を修正しました。');

        $item->refresh();
        $this->assertNotSame($old, $item->img_url);
        $this->assertMatchesRegularExpression('#^image/product/uploads/[A-Za-z0-9]{40}\.(webp|png|jpg)$#', $item->img_url);
        Storage::disk('public')->assertExists($item->img_url);
        Storage::disk('public')->assertMissing($old);
    }

    /**
     * シードの画像（uploads の外）は、差し替えても消さない
     */
    #[Test]
    public function 差し替えてもシードの画像は消さない(): void
    {
        $this->loginAsAdmin();
        Storage::disk('public')->put('image/product/old.png', 'old');
        $item = $this->makeListed('商品', 2, ['img_url' => 'image/product/old.png']);

        $this->update($item, ['image' => UploadedFile::fake()->image('new.png', 20, 20)])->assertSessionHas('list_status', '商品を修正しました。');

        $this->assertNotSame('image/product/old.png', $item->fresh()->img_url);
        Storage::disk('public')->assertExists('image/product/old.png');
    }

    /**
     * 画像を選ばない修正では、uploads の画像も消さない
     */
    #[Test]
    public function 画像を選ばない修正ではuploadsの画像も消さない(): void
    {
        $this->loginAsAdmin();
        $old = ProductImageStorageInterface::DIRECTORY.'/keep.webp';
        Storage::disk('public')->put($old, 'keep');
        $item = $this->makeListed('商品', 2, ['img_url' => $old]);

        $this->update($item, ['price' => '999'])->assertSessionHas('list_status', '商品を修正しました。');

        $this->assertSame($old, $item->fresh()->img_url);
        Storage::disk('public')->assertExists($old);
    }

    /**
     * 更新が失敗したときは、古い画像も消さず、新しく書いた画像だけを消す
     */
    #[Test]
    public function 更新が失敗したときは古い画像を消さず新しい画像を消す(): void
    {
        $old = ProductImageStorageInterface::DIRECTORY.'/old.webp';
        Storage::disk('public')->put($old, 'old');
        $item = $this->makeListed('商品', 2, ['img_url' => $old]);
        $this->mock(ItemRepositoryInterface::class, function (MockInterface $mock) use ($item): void {
            $mock->shouldReceive('update')->once()->andReturnUsing(function (int $id, array $attributes, \Closure $beforeSave) use ($item): never {
                $beforeSave($item, false);

                throw new \RuntimeException('DBのエラー');
            });
        });
        $input = new ItemInput('商品', 2, 330, '紹介', false, false, null, UploadedFile::fake()->image('a.png', 20, 20));

        try {
            app(AdminItemService::class)->update($item->id, $input);
            $this->fail('例外が投げられるはず');
        } catch (\RuntimeException $e) {
            $this->assertSame('DBのエラー', $e->getMessage());
        }

        Storage::disk('public')->assertExists($old);
        $this->assertSame([$old], $this->storedImages());
    }

    /**
     * 更新する商品がなかったときは、何も保存せず、古い画像も消さない
     */
    #[Test]
    public function 商品がなければ画像を保存せず消しもしない(): void
    {
        $this->mock(ProductImageStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->once()->andReturn(new PreparedImage('x', 'webp'));
            $mock->shouldNotReceive('save');
            $mock->shouldNotReceive('delete');
        });

        $input = new ItemInput('商品', 2, 330, '紹介', false, false, null, UploadedFile::fake()->image('a.png', 20, 20));

        $this->assertFalse(app(AdminItemService::class)->update(99999, $input));
    }

    /**
     * デッドロックの再試行で確認の処理が2回呼ばれても、画像は1回しか書かない。古い画像は確定のあとに1回だけ消す
     */
    #[Test]
    public function 再試行されても画像は1回だけ書く(): void
    {
        $item = $this->makeListed('商品', 2, ['img_url' => ProductImageStorageInterface::DIRECTORY.'/old.webp']);
        $this->mock(ItemRepositoryInterface::class, function (MockInterface $mock) use ($item): void {
            $mock->shouldReceive('update')->once()->andReturnUsing(function (int $id, array $attributes, \Closure $beforeSave) use ($item): bool {
                $beforeSave($item, false);
                $beforeSave($item, false);

                return true;
            });
        });
        $this->mock(ProductImageStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->once()->andReturn(new PreparedImage('x', 'webp'));
            $mock->shouldReceive('save')->once()->andReturn(ProductImageStorageInterface::DIRECTORY.'/new.webp');
            $mock->shouldReceive('delete')->once()->with(ProductImageStorageInterface::DIRECTORY.'/old.webp');
        });
        $input = new ItemInput('商品', 2, 330, '紹介', false, false, null, UploadedFile::fake()->image('a.png', 20, 20));

        $this->assertTrue(app(AdminItemService::class)->update($item->id, $input));
    }

    /**
     * 重い画像の処理（prepare）は、DBのトランザクションの前に済ませる。読めない画像は、リポジトリに入る前に image のエラーになる
     */
    #[Test]
    public function 読めない画像はトランザクションに入る前にエラーになる(): void
    {
        $this->mock(ItemRepositoryInterface::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('create');
            $mock->shouldNotReceive('update');
        });
        $broken = UploadedFile::fake()->createWithContent('broken.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 20));
        $input = new ItemInput('商品', 2, 330, '紹介', false, false, null, $broken);

        foreach ([
            fn () => app(AdminItemService::class)->create($input),
            fn () => app(AdminItemService::class)->update(1, $input),
        ] as $call) {
            try {
                $call();
                $this->fail('ValidationException が投げられるはず');
            } catch (ValidationException $e) {
                $this->assertSame(['image' => [GdProductImageStorage::UNREADABLE_MESSAGE]], $e->errors());
            }
        }
    }

    /**
     * 重複のときは、準備した画像を書かない（ファイルを書くのは確認が通ってから）
     */
    #[Test]
    public function 重複のときは画像を書かない(): void
    {
        $this->makeListed('じゃがいも', 2);
        $this->mock(ProductImageStorageInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->once()->andReturn(new PreparedImage('x', 'webp'));
            $mock->shouldNotReceive('save');
            $mock->shouldNotReceive('delete');
        });
        $this->loginAsAdmin();

        $this->store(['name' => 'じゃがいも', 'weight' => '2'])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('items', 1);
    }

    /**
     * 画像の削除は、uploads の中のファイルだけ。シードの画像や、上の階層へたどるパスは消さない
     */
    #[Test]
    public function 削除はuploadsの中のファイルだけ(): void
    {
        $storage = app(ProductImageStorageInterface::class);
        $this->assertInstanceOf(GdProductImageStorage::class, $storage);
        Storage::disk('public')->put('image/product/seed.webp', 's');
        Storage::disk('public')->put('image/product/uploads/a.webp', 'a');

        $storage->delete('image/product/seed.webp');
        $storage->delete('image/product/uploads/../seed.webp');
        $storage->delete('image/product/uploads');
        Storage::disk('public')->assertExists('image/product/seed.webp');
        Storage::disk('public')->assertExists('image/product/uploads/a.webp');

        $storage->delete('image/product/uploads/a.webp');
        Storage::disk('public')->assertMissing('image/product/uploads/a.webp');
    }

    /**
     * jpeg の EXIF の向き（1〜8。2・4・5・7 の鏡像を含む）を、縮小してから正しい向きに直す。
     * 元画像は左上が赤・右上が緑・左下が青・右下が黄の 40×20。期待は、直したあとの左上・右上・左下・右下の色
     *
     * @return array<string, array{0: int, 1: string, 2: array{0: int, 1: int}}>
     */
    public static function orientations(): array
    {
        return [
            '1 そのまま' => [1, 'RGBY', [40, 20]],
            '2 左右反転' => [2, 'GRYB', [40, 20]],
            '3 180度' => [3, 'YBGR', [40, 20]],
            '4 上下反転' => [4, 'BYRG', [40, 20]],
            '5 左右反転して反時計回りに90度' => [5, 'RBGY', [20, 40]],
            '6 時計回りに90度' => [6, 'BRYG', [20, 40]],
            '7 左右反転して時計回りに90度' => [7, 'YGBR', [20, 40]],
            '8 反時計回りに90度' => [8, 'GYRB', [20, 40]],
        ];
    }

    /**
     * @param  array{0: int, 1: int}  $size
     */
    #[Test]
    #[DataProvider('orientations')]
    public function jpegの向きを正しく直す(int $orientation, string $expected, array $size): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('exif 拡張がありません。');
        }

        $prepared = app(ProductImageStorageInterface::class)->prepare($this->jpegWithOrientation($orientation, 40, 20));

        $image = imagecreatefromstring($prepared->binary);
        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame($size, [imagesx($image), imagesy($image)]);
        $w = imagesx($image);
        $h = imagesy($image);
        $colors = '';
        foreach ([[$w / 4, $h / 4], [$w * 3 / 4, $h / 4], [$w / 4, $h * 3 / 4], [$w * 3 / 4, $h * 3 / 4]] as [$x, $y]) {
            $colors .= $this->nearestColor((int) imagecolorat($image, (int) $x, (int) $y));
        }
        $this->assertSame($expected, $colors);
    }

    /**
     * 大きな縦向きの jpeg（向きあり）は、縮小してから回転するので、長辺1200pxに収まり、縦横が入れ替わる（回転は縮小後の小さい画像に対して行う）
     */
    #[Test]
    public function 向きのある大きな画像は縮小してから回転する(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('exif 拡張がありません。');
        }

        $prepared = app(ProductImageStorageInterface::class)->prepare($this->jpegWithOrientation(6, 3000, 1500));

        $size = getimagesizefromstring($prepared->binary);
        $this->assertIsArray($size);
        $this->assertSame([600, 1200], [$size[0], $size[1]]);
    }

    /**
     * 向き（EXIF Orientation）を埋め込んだ jpeg のアップロードを作る（左上が赤・右上が緑・左下が青・右下が黄）
     */
    private function jpegWithOrientation(int $orientation, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(\GdImage::class, $image);
        $half = [intdiv($width, 2), intdiv($height, 2)];
        $quadrants = [[0, 0, 0xFF0000], [$half[0], 0, 0x00FF00], [0, $half[1], 0x0000FF], [$half[0], $half[1], 0xFFFF00]];
        foreach ($quadrants as [$x, $y, $rgb]) {
            imagefilledrectangle($image, $x, $y, $x + $half[0] - 1, $y + $half[1] - 1, $rgb);
        }
        ob_start();
        imagejpeg($image, null, 100);
        $jpeg = (string) ob_get_clean();

        // SOI の直後に、向きだけを持つ EXIF の APP1 を挿入する（ビッグエンディアンの TIFF）
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"."\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return UploadedFile::fake()->createWithContent('photo.jpg', substr($jpeg, 0, 2).$segment.substr($jpeg, 2));
    }

    /**
     * 色を、赤・緑・青・黄のうち一番近いものの頭文字（R・G・B・Y）にする
     */
    private function nearestColor(int $rgb): string
    {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $best = '';
        $bestDistance = PHP_INT_MAX;
        foreach (['R' => [255, 0, 0], 'G' => [0, 255, 0], 'B' => [0, 0, 255], 'Y' => [255, 255, 0]] as $name => [$cr, $cg, $cb]) {
            $distance = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;
            if ($distance < $bestDistance) {
                $best = $name;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * AC-15：修正で入力に誤りがあると、更新されずに修正ページに戻り、エラーと入力が残る。画像の形式の誤りも同じ
     */
    #[Test]
    public function 修正で入力に誤りがあると更新されず修正ページに戻る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('商品', 2, ['price' => 500]);
        $edit = route('admin.items.edit', $item->id);

        $this->update($item, ['name' => '', 'price' => '100001', 'detail' => '残る紹介文'])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['name' => '商品名を入力してください', 'price' => '商品金額は100,000円以下で入力してください']);
        $this->update($item, ['image' => UploadedFile::fake()->image('photo.gif', 20, 20)])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['image' => '商品画像はjpeg・png・webpのいずれかの形式にしてください']);

        $fresh = $item->fresh();
        $this->assertSame('商品', $fresh->name);
        $this->assertSame(500, $fresh->price);
        $this->assertSame([], $this->storedImages());

        // 入力が残る
        $this->update($item, ['name' => '', 'price' => '100001', 'detail' => '残る紹介文']);
        $this->get($edit)->assertOk()->assertSee('value="100001"', false)->assertSee('>残る紹介文</textarea>', false);
    }

    /**
     * AC-15：修正の重複は、自分自身を除いて判定する。何も変えずに修正しても通る。別の商品と同じ組み合わせにするとエラー
     */
    #[Test]
    public function 修正の重複判定は自分自身を除く(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('UAT登録通常', 2);
        $other = $this->makeListed('じゃがいも', 2);
        $this->makeListed('削除済み', 5)->delete();

        // 何も変えずに修正する
        $this->update($item, ['name' => 'UAT登録通常', 'weight' => '2'])->assertSessionHas('list_status', '商品を修正しました。')->assertSessionHasNoErrors();
        // 別の商品と同じ名前・重さ
        $this->update($item, ['name' => 'じゃがいも', 'weight' => '2'])
            ->assertRedirect(route('admin.items.edit', $item->id))
            ->assertSessionHasErrors(['name' => 'じゃがいも 2kg はすでに登録されています']);
        $this->assertSame('UAT登録通常', $item->fresh()->name);
        // 同じ名前でも重さが違えばよい。削除済みの商品と同じ組み合わせもよい
        $this->update($item, ['name' => 'じゃがいも', 'weight' => '5'])->assertSessionHasNoErrors();
        $this->update($other, ['name' => '削除済み', 'weight' => '5'])->assertSessionHasNoErrors();
        $this->assertSame('削除済み', $other->fresh()->name);
    }

    /**
     * AC-16：予約可能数の上限を、すでに予約された数より小さくするとエラーで更新されない。同じ数にはできる。取消済みの予約は数えない
     */
    #[Test]
    public function 上限をすでに予約された数より小さくできない(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeReservable(10, 1450, 200, 'UAT予約商品');
        $this->makeReservedBy($item, 2);
        $this->makeReservedBy($item, 1);
        $canceled = $this->makeReservedBy($item, 50);
        $canceled->forceFill(['canceled_at' => Carbon::now()])->save();
        $edit = route('admin.items.edit', $item->id);
        $limited = ['name' => 'UAT予約商品', 'weight' => '10', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited'];

        $this->update($item, $limited + ['reservation_limit' => '2'])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['reservation_limit' => 'すでに3個の予約があるため、3以上にしてください']);
        $this->assertSame(200, $item->fresh()->reservation_limit);

        $this->update($item, $limited + ['reservation_limit' => '3'])->assertSessionHasNoErrors()->assertRedirect(route('admin.items.index'));
        $this->assertSame(3, $item->fresh()->reservation_limit);
        $this->assertSame('3（残り0）', app(AdminItemService::class)->findDetail($item->id)?->reservationLimitLabel());

        // 上限なし・通常への変更は、予約があってもできる（これまでの予約の記録は残る）
        $this->update($item, [...$limited, 'reservation_limit_type' => 'unlimited'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->reservation_limit);
        $this->update($item, [...$limited, 'is_reserved_only' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $item->fresh()->is_reserved_only);
        $this->assertNull($item->fresh()->reservation_limit);
        $this->assertSame(3, Reservation::where('item_id', $item->id)->count());
    }

    /**
     * AC-16：重複と予約済みの数のエラーは、同時に起きたら両方を出す
     */
    #[Test]
    public function 重複と予約済みの数のエラーは同時に出る(): void
    {
        $this->loginAsAdmin();
        $this->makeReservable(5, 1000, 100, '北の恵み');
        $item = $this->makeReservable(10, 1450, 200, '北の恵みセット');
        $this->makeReservedBy($item, 5);

        $this->update($item, ['name' => '北の恵み', 'weight' => '5', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '4'])
            ->assertSessionHasErrors(['name' => '北の恵み 5kg はすでに登録されています', 'reservation_limit' => 'すでに5個の予約があるため、5以上にしてください']);
    }

    /**
     * AC-17：通常の商品を限定に変えると、カートに入っていてもカートから取り除かれ、通常に戻すとカートに入れられる
     */
    #[Test]
    public function 通常と限定を切り替えるとカートの扱いが変わる(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('切り替える商品', 2);
        $user = User::factory()->create();

        $this->update($item, ['name' => '切り替える商品', 'is_reserved_only' => '1', 'reservation_limit_type' => 'unlimited'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) $item->fresh()->is_reserved_only);
        Auth::shouldUse('web');
        $this->actingAs($user)->withSession(['cart' => [$item->id => 2]])->get(route('shop.cart'))->assertOk();
        $this->assertSame([], session('cart'));

        $this->loginAsAdmin();
        $this->update($item, ['name' => '切り替える商品', 'is_reserved_only' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $item->fresh()->is_reserved_only);
        $this->assertSame(CartAddResult::Added, app(CartService::class)->addItem($item->id));
    }

    /**
     * AC-18：削除済み・存在しない商品を更新しようとすると、何も変えずに一覧に戻って「商品が見つかりません。」。画像も残らない。idの形が正しくなくても同じ
     */
    #[Test]
    public function 削除済みや存在しない商品の更新は何も変えず一覧に戻る(): void
    {
        $this->loginAsAdmin();
        $gone = $this->makeListed('削除済み', 2);
        $gone->delete();
        $before = $gone->fresh();

        $cases = [
            $this->input(['id' => (string) $gone->id, 'name' => '変えた名前', 'image' => UploadedFile::fake()->image('new.png', 20, 20)]),
            $this->input(['id' => '99999', 'name' => '変えた名前', 'image' => UploadedFile::fake()->image('new.png', 20, 20)]),
            $this->input(['id' => 'abc', 'name' => '変えた名前']),
            $this->input(['id' => '0', 'name' => '変えた名前']),
            $this->input(['id' => ['1'], 'name' => '変えた名前']),
            ['name' => '変えた名前'],
        ];
        foreach ($cases as $data) {
            $this->from(route('admin.items.edit', $gone->id))->post(route('admin.items.update'), $data)
                ->assertRedirect(route('admin.items.index'))
                ->assertSessionHasErrors(['item' => '商品が見つかりません。']);
        }

        $this->assertDatabaseCount('items', 1);
        $this->assertSame('削除済み', Item::withTrashed()->find($gone->id)->name);
        $this->assertTrue(Item::withTrashed()->find($gone->id)->trashed());
        $this->assertEquals($before->updated_at, Item::withTrashed()->find($gone->id)->updated_at);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * 修正ページを開いたあとに商品が削除されても、更新すると一覧に戻って「商品が見つかりません。」で、削除された商品は変わらない
     */
    #[Test]
    public function 開いたあとに削除された商品を更新すると一覧に戻る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('商品', 2);
        $this->get(route('admin.items.edit', $item->id))->assertOk();
        $item->delete();

        $this->update($item, ['name' => '変えた名前'])
            ->assertRedirect(route('admin.items.index'))
            ->assertSessionHasErrors(['item' => '商品が見つかりません。']);
        $this->assertSame('商品', Item::withTrashed()->find($item->id)->name);
    }

    /**
     * AC-19：修正しても、過去の注文・予約の商品名・重さ・金額（注文した時点の値）は変わらない
     */
    #[Test]
    public function 修正しても過去の注文と予約の値は変わらない(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('じゃがいも', 2, ['price' => 330]);
        $purchase = Purchase_history::factory()->create();
        $line = Purchase_history_item::factory()->create(['purchase_history_id' => $purchase->id, 'item_id' => $item->id, 'quantity' => 3, 'price' => 330, 'subtotal' => 990]);
        $reserved = $this->makeReservable(10, 1450, 200, '北の恵みセット');
        $reservation = $this->makeReservedBy($reserved, 2);
        $lineBefore = $line->fresh();
        $reservationBefore = $reservation->fresh();

        $this->update($item, ['name' => '名前を変えた', 'weight' => '10', 'price' => '999'])->assertSessionHasNoErrors();
        $this->update($reserved, ['name' => '別の名前', 'weight' => '5', 'price' => '2', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '10'])->assertSessionHasNoErrors();

        $this->assertSame('名前を変えた', $item->fresh()->name);
        $line = $line->fresh();
        $this->assertSame($lineBefore->item_name, $line->item_name);
        $this->assertSame('じゃがいも', $line->item_name);
        $this->assertSame(2, $line->item_weight);
        $this->assertSame(330, $line->price);
        $this->assertSame(990, $line->subtotal);
        $reservation = $reservation->fresh();
        $this->assertSame($reservationBefore->item_name, $reservation->item_name);
        $this->assertSame('北の恵みセット', $reservation->item_name);
        $this->assertSame(10, $reservation->item_weight);
        $this->assertSame(1450, $reservation->price);
    }

    /**
     * AC-21：ページの有効期限が切れた（419）とき、登録は登録ページへ、修正は修正ページへ、画像以外の入力を残して戻る（登録・更新はされない）
     */
    #[Test]
    public function トークンが古いときは登録ページと修正ページに入力を残して戻る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed('商品', 2);
        $this->app['env'] = 'local';
        $create = route('admin.items.create');
        $edit = route('admin.items.edit', $item->id);

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.items.store'), ['_token' => 'stale-token', 'name' => '残る名前', 'weight' => '5', 'detail' => '残る紹介文', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])
            ->assertRedirect($create)
            ->assertSessionHasErrors(['form' => 'ページの有効期限が切れました。もう一度登録してください。']);
        $html = $this->get($create)->assertOk()->getContent();
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">ページの有効期限が切れました。もう一度登録してください。</p>', $html);
        $this->assertStringContainsString('value="残る名前"', $html);
        $this->assertStringContainsString('>残る紹介文</textarea>', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="weight"[^>]*value="5"[^>]*\schecked(?![:\w-])/', $html);

        $this->withSession(['_token' => 'valid-token'])
            ->post(route('admin.items.update'), ['_token' => 'stale-token', 'id' => (string) $item->id, 'name' => '変えた名前', 'detail' => '残る紹介文'])
            ->assertRedirect($edit)
            ->assertSessionHasErrors(['form' => 'ページの有効期限が切れました。もう一度修正してください。']);
        $html = $this->get($edit)->assertOk()->getContent();
        $this->assertStringContainsString('<p class="mt-6 text-base text-danger" role="alert">ページの有効期限が切れました。もう一度修正してください。</p>', $html);
        $this->assertStringContainsString('value="変えた名前"', $html);
        $this->assertStringContainsString('>残る紹介文</textarea>', $html);

        $this->assertDatabaseCount('items', 1);
        $this->assertSame('商品', $item->fresh()->name);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * 419で、修正する商品のIDが正しくないときは一覧へ戻す。管理者がログインしていなければログインページへ戻す
     */
    #[Test]
    public function トークンが古いときの戻り先はログインと_i_dの形で変わる(): void
    {
        $this->app['env'] = 'local';
        $stale = ['_token' => 'stale-token'];

        $this->withSession(['_token' => 'valid-token'])->post(route('admin.items.store'), $stale)->assertRedirect(route('admin.login'));
        $this->withSession(['_token' => 'valid-token'])->post(route('admin.items.update'), $stale + ['id' => '1'])->assertRedirect(route('admin.login'));

        $this->loginAsAdmin();
        foreach ([[], ['id' => 'abc'], ['id' => '0'], ['id' => ['1']]] as $extra) {
            $this->withSession(['_token' => 'valid-token'])->post(route('admin.items.update'), $stale + $extra)
                ->assertRedirect(route('admin.items.index'))
                ->assertSessionHasErrors(['item' => 'ページの有効期限が切れました。もう一度修正してください。']);
        }
    }

    /**
     * 処理の最初と最後にログを出す
     */
    #[Test]
    public function 処理の最初と最後にログを出す(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed();
        Log::spy();

        $this->get(route('admin.items.create'));
        $this->store(['name' => 'ログ用']);
        $this->get(route('admin.items.edit', $item->id));
        $this->update($item);

        foreach (['create', 'store', 'edit', 'update'] as $method) {
            Log::shouldHaveReceived('debug')->with("AdminItemController@{$method} start")->once();
            Log::shouldHaveReceived('debug')->with("AdminItemController@{$method} end")->once();
        }
    }

    /**
     * 一覧の「修正」のリンクはルート名で作られ、押すと修正ページが開く。一覧の表示に登録・修正の案内が出る
     */
    #[Test]
    public function 一覧の修正のリンクから修正ページが開く(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed();

        $this->get(route('admin.items.index'))->assertSee('href="'.route('admin.items.edit', $item->id).'"', false);
        $this->get(route('admin.items.edit', $item->id))->assertOk();
        $this->withSession(['list_status' => '商品を登録しました。'])->get(route('admin.items.index'))
            ->assertSee('<p class="mt-6 w-full text-center text-base text-accent-hover" role="status">商品を登録しました。</p>', false);
    }

    /**
     * 商品金額・予約可能数は、全角数字を半角にし、前後の空白（全角を含む）を除いてから検証する（登録・修正とも）
     */
    #[Test]
    public function 全角の数字と前後の空白は直してから検証する(): void
    {
        $this->loginAsAdmin();

        $this->store(['name' => '全角', 'price' => '１５００', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '２００'])->assertSessionHasNoErrors();
        $this->store(['name' => '空白', 'price' => ' 1800 ', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => "\u{3000}５０\u{3000}"])->assertSessionHasNoErrors();
        $this->store(['name' => '全角の空白', 'price' => "\u{3000}９９９\u{3000}"])->assertSessionHasNoErrors();

        $this->assertSame([1500, 200], [Item::where('name', '全角')->sole()->price, Item::where('name', '全角')->sole()->reservation_limit]);
        $this->assertSame([1800, 50], [Item::where('name', '空白')->sole()->price, Item::where('name', '空白')->sole()->reservation_limit]);
        $this->assertSame(999, Item::where('name', '全角の空白')->sole()->price);

        // 修正でも同じ。数字でないものは、変換しても整数にならないのでエラー
        $item = Item::where('name', '全角')->sole();
        $this->update($item, ['name' => '全角', 'price' => '２５００', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '３００'])->assertSessionHasNoErrors();
        $this->assertSame([2500, 300], [$item->fresh()->price, $item->fresh()->reservation_limit]);
        $this->update($item, ['name' => '全角', 'price' => '１２．５'])->assertSessionHasErrors(['price' => '商品金額は整数で入力してください']);
        $this->update($item, ['name' => '全角', 'price' => 'あ'])->assertSessionHasErrors(['price' => '商品金額は整数で入力してください']);
        $this->update($item, ['name' => '全角', 'price' => '　　'])->assertSessionHasErrors(['price' => '商品金額を入力してください']);
    }

    /**
     * 画像の選択・ドロップの検証（形式・大きさ）と、DataTransfer が使えないときの案内は、ブラウザ側（Alpine）で行う。必要な値と文言がページに出る
     */
    #[Test]
    public function ブラウザ側で画像の形式と大きさを確かめる仕組みがページに出る(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeListed();

        foreach ([route('admin.items.create'), route('admin.items.edit', $item->id)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('maxBytes: 10485760', $html);
            $this->assertStringContainsString("types: ['image/jpeg', 'image/png', 'image/webp']", $html);
            $this->assertSame('jpeg・png・webp の10MB以下の画像を選んでください', ItemStoreRequest::IMAGE_HINT);
            $this->assertStringContainsString((string) Js::from(ItemStoreRequest::IMAGE_HINT), $html);
            $this->assertStringContainsString('「画像を選択」から選んでください', $html);
            $this->assertStringContainsString('new DataTransfer()', $html);
            $this->assertStringContainsString('catch (e)', $html);
            $this->assertStringContainsString('id="image-client-error"', $html);
        }
    }

    /**
     * 画像は、DBへの登録が失敗したときだけ消す（保存したあとに失敗したとき）。保存の前の失敗では、そもそも保存しない
     */
    #[Test]
    public function 保存のあとに_d_bの登録が失敗したときだけ画像を消す(): void
    {
        $uploadedDuringSave = null;
        $this->mock(ItemRepositoryInterface::class, function (MockInterface $mock) use (&$uploadedDuringSave): void {
            $mock->shouldReceive('create')->once()->andReturnUsing(function (array $attributes, \Closure $beforeSave) use (&$uploadedDuringSave): never {
                $beforeSave(false);
                $uploadedDuringSave = $this->storedImages();

                throw new \RuntimeException('DBのエラー');
            });
        });
        $input = new ItemInput('商品', 2, 330, '紹介', false, false, null, UploadedFile::fake()->image('a.png', 20, 20));

        try {
            app(AdminItemService::class)->create($input);
            $this->fail('例外が投げられるはず');
        } catch (\RuntimeException $e) {
            $this->assertSame('DBのエラー', $e->getMessage());
        }

        $this->assertCount(1, $uploadedDuringSave);
        $this->assertSame([], $this->storedImages());
    }

    /**
     * 商品がない・重複・予約済みの数のエラーのときは、画像を保存しない（保存してから消すこともしない）
     */
    #[Test]
    public function 確認が通らないときは画像を保存しない(): void
    {
        $this->loginAsAdmin();
        $item = $this->makeReservable(10, 1450, 200, 'UAT予約商品');
        $this->makeReservedBy($item, 5);
        $other = $this->makeListed('別の商品', 2);
        Storage::spy();

        $this->update($item, ['name' => 'UAT予約商品', 'weight' => '10', 'is_reserved_only' => '1', 'reservation_limit_type' => 'limited', 'reservation_limit' => '1', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])->assertSessionHasErrors('reservation_limit');
        $this->update($item, ['name' => '別の商品', 'weight' => '2', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])->assertSessionHasErrors('name');
        $this->update($other, ['id' => '99999', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])->assertSessionHasErrors('item');
        $this->store(['name' => '別の商品', 'weight' => '2'])->assertSessionHasErrors('name');

        Storage::shouldNotHaveReceived('disk');
    }

    /**
     * 商品名と重さの索引がある（同じ組み合わせを探してロックするときに全件を走査しないため）
     */
    #[Test]
    public function 商品名と重さの索引がある(): void
    {
        $this->assertTrue(Schema::hasIndex('items', ['name', 'weight']));
    }
}
