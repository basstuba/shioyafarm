---
paths:
  - "src/resources/views/**"
  - "src/resources/css/**"
---

# フロントエンドの規約

## Figmaのデザイン

すべての画面のデザインはFigmaにあり、実装のたびにユーザーがリンクを渡す。

- PC版のリンクを使う。SP版のデザインはまだ作っていないため、下の「SP版のデザインを作る」の手順でユーザーと相談して作る。SP版のリンクが渡された場合はそちらを使う。
- Figma MCPで取得したコードは参考情報として扱い、そのまま貼り付けない。
  - Tailwindのクラスはそのまま使ってよいが、Reactやインラインスタイルは使わずBladeに書き直す。
  - 色・フォントは `resources/css/app.css` の `@theme` に定義したもの（`text-ink`、`bg-cream-200`、`font-maru` など）を使う。`bg-[#fff4bf]` のように色を直接書かない。新しい色が出てきたら `@theme` に追加する。
  - SP版は同じクラスにブレークポイントの接頭辞（`md:` など）を付けて書く。モバイルファーストなので、接頭辞なしがSP、`md:`（768px以上）・`lg:`（1024px以上）がタブレット・PCになる。

### SP版のデザインを作る

SP版のリンクがない場合は、Figmaにフレームを作らず（Figma MCPの呼び出し上限を節約するため）、文章で方針を決めてから実装する。推測で実装しない。

1. PC版のデザインの値をもとに、SP版のレイアウト方針（縦積みにする部分、余白・文字サイズ、表をカード形式にするか、モーダルの幅など）を短く提案し、ユーザーのOKをもらう。色・フォント・コンポーネントはPC版のものを使う。管理画面は高齢の生産者向けに、文字やボタンを大きめにする。
2. OKが出た方針で実装する。
3. 実際の画面を幅414pxで開いたスクリーンショットをユーザーに見せ、OKが出るまで直す。

### 単位

Figmaの数値（px）はそのまま使う。画面幅に比例して拡大縮小する仕組みは使わない（ブラウザの文字サイズ設定が効くようにするため）。

- 余白・幅はTailwindの目盛り（1 = 4px）に直して書く（例：`padding: 40px` → `p-10`、`gap: 60px` → `gap-15`）。4の倍数でない値だけ `w-[62px]` のように書く。
- 文字サイズは `text-xs`(12px)・`text-sm`(14px)・`text-base`(16px)・`text-xl`(20px)・`text-2xl`(24px) などを使い、当てはまらない値だけ `text-[28px]` のように書く。
- PCデザイン（幅1920px）の左右の余白は、`mx-auto max-w-[1520px] px-4` のように最大幅で表す。画面が狭くなっても横スクロールが出ないようにするため、固定の `px-[200px]` は使わない。
- 固定ヘッダーの高さは `h-header` / `pt-header`（70px）を使う。
- 画像はFigmaから書き出したものを `.webp` に変換し、下の「画像」の規約に従って置く。既存の画像と同じものがあれば、そちらを使う。
- 実装後はFigmaのスクリーンショットと実際の画面を見比べて、違いがあれば報告する。

## Blade

- ビューは `resources/views/{shop,admin,auth,layouts,shipping}` に分かれている。トップページや静的ページは `resources/views` の直下に置く。
- ユーザー画面は `layouts/app`、管理画面のトップページは `layouts/admin/top`（サイドメニューなし）、それ以外の管理画面は `layouts/admin/app`（サイドメニュー付き）、認証画面（ログイン・会員登録）は `layouts/auth`、サンクスページ（お問い合わせ・購入・予約の完了）は `layouts/thanks` を継承する。
- `<head>`（メタ・フォント・favicon・`@vite`）は `layouts/head` にまとめてあり、各レイアウトから `@include` する。レイアウトに直接書かない。`<title>` は `@section('page-title')` で変えられる（省略時は「Shioya Farm」）。
- `layouts/app` のセクション：
  - `@section('body-class')`：`<body>` に付けるクラス（背景色の `bg-cream-100` や、フッターの色を変える目印の `legal-page` など）
  - `@section('content')`：`<main>` の中身
- 管理画面のヘッダー（`layouts/admin/header`）とフッター（`layouts/admin/footer`）は、トップページ（`layouts/admin/top`）とそれ以外（`layouts/admin/app`）で共通。ヘッダーはPC 1行・SP 2段（タイトルの下に「サイトを見る」「ログアウト」。サイドメニューのあるページではSPの2段目の左に「メニュー」ボタン）。`@include('layouts.admin.header', ['withMenu' => true])` でボタンを出す。タイトルは、トップページだけ h1。それ以外のページは本文のページタイトルを h1 にし、ヘッダーのタイトルは `<p>`（見出しの階層を二重にしない）。本文は `font-sans`、フッターの © だけ `font-maru`。色の地は `@theme` の `admin-bar`・`admin-bar-end`。管理画面の色調は白・黒・グレーで、公開側の橙系は流用しない（灰色は `admin-gray`・`admin-head`・`admin-dark`・`admin-shipped`）。
- `layouts/admin/top` は管理画面のトップページ専用のレイアウト。`min-h-dvh` の縦積み（スマホのアドレスバーで高さがずれないよう `dvh`）で `<main>` を伸ばし、本文を縦横中央に置く（画面が低くても重ならない）。セクションは `page-title`・`content`。flash の `status` があれば、レイアウトがヘッダーの下に `<x-admin-status>` で案内を出す（出ている間、カードはその分下がり、残りの領域の上下中央に置かれる）。
- `layouts/admin/app` はトップ以外の管理画面のレイアウト。`min-h-dvh` の縦積みで、ヘッダーとフッターの間に `layouts/admin/sidebar`（サイドメニュー）と `<main>`（本文）を横に並べる（SPでは縦）。セクションは `page-title`・`content`（本文の先頭にページタイトルの h1 を置く）。`@livewireStyles` / `@livewireScripts` を読み込み、Livewire同梱のAlpine.jsを使う（別に入れない。素のJSは書かない）。flash の `status` の案内はトップと同じ。
  - サイドメニューの開閉の状態は `<body>` の `x-data` が持つ。`open`（PCで開いているか。Alpine の `$persist(true).as('admin-sidebar-open')` で localStorage に保存し、再読み込み・ページ移動でも保つ）と `spOpen`（SPでメニューを開いているか。保存しない＝ページを移ると閉じる。PCの状態は使わない）。管理者用ログイン画面（`auth/admin/login`）の `x-init` で保存を消すため、ログイン直後は開く。
  - サイドメニュー：PC（`lg:` 以上）は幅200px（地 `admin-bar`・右に1pxの `line`）。畳むと幅56pxの帯になり、縦書きの「メニュー」ボタン（`aria-label`「メニューを開く」、`aria-expanded` を切り替える）だけを残す。SPはヘッダーの「メニュー」ボタンで本文の上に開閉する。開閉のクラスは `:class` で切り替え、サーバーが出す class は初期状態（PCは開、SPは閉）と同じにする。保存された状態と違う見た目が一瞬出ないよう、Alpine が起動するまで `<aside>` を `x-cloak` で隠す。
  - サイドメニューの開閉アニメーション（約200ms）：`<body>` の `ready`（Alpine の起動後に `x-init` の `$nextTick` で true にする）が true になってから transition のクラスを `:class` で付ける（再読み込み・ページ移動では動かさない）。クラスは `motion-safe:` 付きで、動きを減らす設定のときは動かさない。PCは `<aside>` の幅（`lg:w-50` ⇔ `lg:w-14`）を変え、`<aside>` は `lg:overflow-hidden`、メニューは幅200px固定（`lg:w-50`）のまま隠し、メニューと畳んだときの帯は `opacity` で入れ替える。SPはメニュー（`#admin-menu`）の `grid-template-rows` を `0fr` ⇔ `1fr` にして高さを変える（`x-collapse` は使わない。余白は内側の要素に付け、外側の枠は `min-h-0 overflow-hidden` の1段を挟む）。閉じている側は `invisible`（`visibility: hidden`）にして Tab でフォーカスが入らないようにする（transition の終わりに切り替わるので、閉じる動きの間は見える）。PCで開閉ボタンを押したら、visibility が切り替わった後（`requestAnimationFrame`）に反対側のボタンへフォーカスを移す。
  - リンクは `App\Support\AdminNav`（`home()`・`groups()`・`isCurrent()`）にまとめ、サイドメニューと管理画面トップのカードで共有する。表示中のページは太字＋地 `admin-bar-end`（`aria-current="page"`）。未実装の画面は `url()` で書いてあり、各画面のブランチで `route()` に置き換える。
- 管理画面の一覧ページ（注文一覧・予約一覧が参考実装）：本体は共通の `admin/partials/order-list`（`$listType`＝`App\Enums\AdminListType`、`$records`＝ページネーター、`$conditions` を渡して `@include` する。注文・予約の語、リセット・検索・発送済みの更新のルート、「詳細」ボタンのイベント名は、`AdminListType`（`subject()`・`listRoute()`・`searchRoute()`・`updateRoute()`・`detailEvent()`・`table()`）から決まるので、ページごとに渡さない）。`admin/orders`・`admin/reservations` は、この部品と詳細モーダルの Livewire コンポーネントを置くだけにする。ページタイトルは中央（SP 28px・PC 32px・`accent-hover`）。検索は GET のフォームで、ラベルはPCでは `sr-only`・SPでは表示する。表はPCでは実際の `table` 要素（最大幅960px・列の幅は割合）、SPでは同じ `table` を `grid` でカード形式にする。ページ送りは `admin/partials/pager`（リンクの通常のページ送り・灰色）。詳細はモーダルで、中身は共通の `admin/partials/order-detail-modal`（`$detail`・`$subject`・`$titleId`）。Livewire の `AdminPurchaseDetail`・`AdminReservationDetail` が表示し、共通の処理は `app/Livewire/Concerns/WithAdminDetailModal`（管理者のログイン確認・開閉・IDの固定。詳細の型は問わず、会員一覧の `AdminUserDetail` も使う）に置く。検索フォームと詳細モーダルの外枠は、下の会員一覧の部品（`<x-admin-search-form>`・`<x-admin-detail-modal>` など）で共有する。一覧のボタンから `Livewire.dispatch('open-purchase-detail' / 'open-reservation-detail', { id })`（イベント名は `AdminListType::detailEvent()`。コンポーネントの `#[On(...)]` と同じ名前）で開く。チェックの即時保存（注文・予約の発送済み、お問い合わせの対応済み）は共通のBladeコンポーネントにしてある。外枠 `<x-admin-instant-save>`（`url`＝更新のルート、`field`＝JSON の項目名 `shipped`／`addressed`、`not-found-message`＝404 の案内、`conflict-message`＝409 の案内（省略可。注文・予約の取消済み）。Alpine の `save(id, checked)` が `fetch` で `{ id, [field]: checked }` を JSON で送り、失敗したら false を返して `role="status"` で案内する。ログイン・ページの期限切れ 401・419 と、それ以外・通信エラーの文言は部品の中）、その中の行 `<x-admin-check-row :checked :active>`（`tr`。地は `admin-shipped` と白・灰色の交互を Alpine の `checked` で切り替える。`active=false` は取消済みの行でチェックを持たない）、行の中のチェック `<x-admin-check-box :id :checked :label :text>`（24px・押せる範囲は高さ44px・保存中は `aria-busy` で二重送信を防ぎ、失敗したらチェックを戻す。`label` は aria-label でチェックの状態で変わらない形にする。例：「注文12の発送済み」「山田 太郎（2026/11/08）のお問い合わせの対応済み」）。管理者のログインが必要なページは `no-store`（`NoStore` ミドルウェア）。
- 管理画面の会員一覧：列が違うため、本体は共通の `order-list` を使わず `admin/users`（見出し・検索・表・ページ送り `admin/partials/pager`）に書く。詳細は Livewire の `AdminUserDetail`（`WithAdminDetailModal` を使い、数行）で、ビューは `livewire/admin-user-detail`（姓・名・住所・電話番号・メールアドレス。退会済みだけ退会日と赤い「削除する」→ 確認のモーダル → `DELETE admin.users.delete`。確認の状態 `confirming`・送信中 `deleting` は Alpine。確認を開くときに `deleting` を戻す。ボタンは管理画面用の `<x-admin-small-button>`＝`variant` danger／cancel、130×30・文字は本文と同じ font-sans（SPは高さ44px・文字16px、PCは文字14px）。マイページ専用の `<x-small-button>`（font-maru 固定）は管理画面では使わない。SP／PCの切り替えは管理画面のレイアウトと同じ `lg:`）。確認のモーダルは `x-trap`（Livewire 同梱の Alpine）でフォーカスを閉じ込め、最初のフォーカスは autofocus の「キャンセル」、閉じたら「削除する」へ戻る。注文一覧・予約一覧と共通の部品はBladeコンポーネントにしてある。検索フォームは `<x-admin-search-form>`（`action`・`label`・`resetHref`。「検索」「リセット」のボタンと、操作が成功した案内（flash の `list_status`。背景・枠なしの緑 `accent-hover`・`role="status"`。layout の `status`＝`x-admin-status` とは別のキーで、二重に出さない）、`$errors` の一覧つき）の中に、`<x-admin-search-field>`（`label`・`for`・`width`）で包んだ `<x-admin-search-input>`・`<x-admin-search-select>` を置く（大きさ・枠・フォーカスは `app.css` の `@utility admin-search-control` で共通。選択欄は `blank-label`＝何も選ばない選択肢の表示（PC・SPとも同じ。「すべて」には切り替えない）と `blank-selected` を props で受け取る）。ボタンは `<x-admin-search-button>`（`variant`＝search／reset／export、`href` があれば `<a>`、`disabled`）。詳細モーダルの外枠（背景・パネル・「閉じる」・Esc）は `<x-admin-detail-modal>`（`titleId`・`title`・`state`・`close`・`escape`。スロット `actions`・`after`）で、注文・予約の中身 `admin/partials/order-detail-modal` も使う。表の「詳細」ボタンは `<x-admin-detail-button>`（`label`・`event`・`id`。注文・予約・会員の一覧で共通）。詳細の1項目（ラベル 16px 太字 → 値 14px → 線）は `<x-admin-detail-item label>`（`bordered=false` で線なし。商品内訳）で、注文・予約・会員の詳細で共通。選択欄の矢印はブラウザ標準を使わず（`appearance-none`・右の余白 `pr-10`）、select を `relative` の div で包み、その上に重ねた span（`pointer-events-none`・右端から16px・`bg-line-strong`＝#acacac を `clip-path` で下向きの三角形にしたもの。Figma 157:2）で描く（注文・予約・会員・お問い合わせの4画面の検索欄に効く。日付の入力欄は変えない）。強制カラー（Windows のハイコントラスト）では三角形が描かれないため、`forced-colors:hidden` で隠し、select に `forced-colors:appearance-auto` を付けて標準の矢印に戻す。名前の検索欄のルールと空白の除去は `App\Support\SearchName`（`RULES`・`normalize()`）に置き、注文・予約・会員の検索条件と入力の検証が使う。`WithAdminDetailModal` は `@template TDetail` を持ち、使う側が `@use WithAdminDetailModal<OrderDetail>` などで型を指定する。
- 管理画面のお問い合わせ一覧：会員一覧と同じく、本体は `admin/inquiries`（検索は対応状況の選択欄と問い合わせ日、表は対応済・問い合わせ日・氏名・詳細・返信の5列、最後の「返信」は会員一覧の「メール送信」と同じ右寄せの青い下線リンクで、リンク先は `route('admin.mail.inquiry', ID)`。会員一覧の「メール送信」は `route('admin.mail.user', ID)`）。対応済みのチェックは注文一覧の発送済みのチェックと同じ共通部品（`<x-admin-instant-save>`・`<x-admin-check-row>`・`<x-admin-check-box>`。`admin.inquiries.update` へ `addressed` を送る。対応済みの行の地は `admin-shipped`）。詳細は Livewire の `AdminInquiryDetail`（`WithAdminDetailModal` を使い、ビューは `livewire/admin-inquiry-detail`。問い合わせ日・氏名・メールアドレス・問い合わせ内容の4項目で、内容は `whitespace-pre-wrap` で改行をそのまま表示し、長いときは枠の中をスクロール）。
- 管理画面のメール作成：本体は `admin/mail_create`（レイアウトは他の一覧と同じ `layouts/admin/app`。サイドメニューに項目はない）。ページタイトルと「送信する」は青（`mail-link`。管理画面で青を使うのはこの2つと一覧の「メール送信」「返信」のリンクだけ）。白いカードに送信先（表示のみ）・メールタイトル・メール本文・「送信する」を縦に並べる（Figma 183:2。送信ボタンはPCで幅400pxの中央寄せ）。送信の前に確認モーダル（会員の削除と同じく `x-trap`、ボタンは `<x-admin-small-button>` の `send`（青）／`cancel`）。フォームは宛先の種類・ID・戻り先（`back`）だけを hidden で送り、メールアドレスは送らない。宛先の種類・IDの解析は `App\Support\MailTarget::fromInput` に1か所でまとめる。戻り先は `MailTarget::listUrl`（old の back → クエリの `?back=` → `url()->previous()` の順に、その種類の一覧・検索のURLだけを `AdminListUrl::tryResolve` で採用。なければ一覧の1ページ目）。入力エラー・送信失敗・419 で作成ページに戻すときは `MailTarget::createUrl` で `?back=` 付きにし、再読み込みしても戻り先を保つ。タイトル欄の Enter は日本語入力の変換確定（`isComposing`・keyCode 229）では確認モーダルを開かない。
- 管理画面の案内（flash の `status`。ログアウトの有効期限切れなど）は `<x-admin-status>`（灰色の枠の `role="status"`。幅や余白は `class` で渡す）。管理者用のログイン・パスワード再設定の3画面と管理画面トップで使う。
- `layouts/thanks` は、共通ヘッダー・フッターを表示せず、`<main>` の中身だけを出す（Figmaのサンクスページにヘッダー・フッターがないため）。セクションは `page-title`・`body-class`・`content`。
- `layouts/auth` は、共通ヘッダー・フッター（`layouts.header` / `layouts.footer`）を使わず、認証ページ用のヘッダー（ロゴ）と © を持つ。PC（`lg:` 以上）は左にフォーム、右に背景画像のパネルの2分割、SPは画面全体に背景画像を敷いて縦に並べる。セクション：
  - `@section('form')`：左側（SPでは見出しの下）のフォーム
  - `@section('heading')`：右側のパネル（SPでは上部）の見出し
  - `@section('guide')`：見出しの下の案内文とリンクボタン（ログインページなら会員登録への案内）
  - 入力欄は `<x-auth-input>`（ラベル・input・エラーメッセージをまとめたもの）を使う。
  - `@section('auth-bg', 'xxx.webp')`：背景画像のファイル名（`storage/image/tool/` 以下。省略時は公開側の `login-page-back-image.webp`。SPの全面背景とPCのパネルの両方に使う）。管理者用ログインは `admin-login-page-back-image.webp`
  - `@section('logo-plain', '1')`：定義するとロゴをリンクにしない（管理者用ログイン。省略時はトップへのリンク）
  - `guide` を定義しないページは、SPで案内の分の余白を出さない（PCはパネルの縦の並びを保つため余白を残す）
  - 管理者用ログイン（`auth/admin/login`）は `auth-direction` に `lg:flex-row-reverse` を指定して左パネルにし、見出しは黒（`text-ink`）、送信ボタンは `<x-auth-submit variant="accent">`
- 何度も出てくる部品はBladeコンポーネント（`resources/views/components/`）にする。ページの見出しは `<x-page-title>` を使う（`layouts/auth` の見出し、お問い合わせフォームのカード内の見出し、商品詳細の商品名（カードの中で画像の横に置く）、カートの見出し（緑・32px・左寄せ）、購入ページの見出し（緑・32px・中央寄せ）、マイページのセクション見出し（緑・24px・中央寄せ。`<x-section-title>`）、登録情報変更ページの見出し（カードの中の緑・32px・中央寄せの h1）はデザインが違うため除く）。
- ボタンは `<x-filled-button>`（橙の塗り）と `<x-outline-button>`（白地に橙の枠線）で、どちらもクラスベースのコンポーネント（`app/View/Components/FilledButton.php`・`OutlineButton.php`）。props は `FilledButton` が `href`・`type`・`size`・`variant`、`OutlineButton` が `href`・`type`・`size`。props の説明と、大きさ・色・影・disabled のクラスの組み立て（`classes()`・`disabledClasses()`）はクラス側にあり、Blade は `<a>`／`<button>` の出し分けと `$attributes->class(...)` だけを書く。大きさや色を足したら、影や既定値などの分岐が変わる場合だけ `tests/Unit` のクラスのテストを直す（クラスの文字列は `tests/Feature` の `Blade::render` のテストで確認する）。
- 入力欄の Blade 部品（`form-field`・`auth-input`・`auth-select`）が `old()` の値を取り出すときは、`App\Support\OldInput::scalar($name, $default)` を使う（配列など文字列にできない値は空文字にする）。部品ごとに `is_scalar` の判定を書かない。Livewire 側のフォーム（`WithDeliveryForm`・`PurchaseForm`）の old の復元は trim などの別の処理もあるため対象外。
- 選択欄（`<select>`）は、Figmaの茶色の三角の矢印が付いた `<x-select>` を使う（`auth-select`・お届け先住所・住所追加モーダルの都道府県で共通）。エラー文には id を付け、`aria-describedby` でつなぐ。
- 購入ページと予約ページで共通の部品：商品の行は `<x-checkout-line>`（数量の input の属性・`wire:change` の式・エラー文のスロットを渡す。列の幅は予約ページ＝860pxにそろえてある）、金額のカードは `livewire/partials/order-summary`（`$summary` と上の余白 `$topClass` を渡して `@include`）。
- マイページ専用の部品：セクション見出しは `<x-section-title>`（h2）。退会手続きの小さなボタン（130×30、SPは高さ44）は `<x-small-button>`（`variant` は danger＝「退会する」／cancel＝「キャンセル」）。履歴の表（`livewire/partials/history-table`）・ページ送り（`livewire/partials/pager`。`$topClass` で上の余白を渡す）・詳細モーダル（`livewire/partials/order-detail-modal`）は購入履歴と予約履歴で共通。
- `<x-auth-input>` の `variant`：default（公開側。枠線・フォーカス枠は橙系 `primary-dark`、「表示」の文字は `primary-text`）／neutral（管理画面。枠線は `line-strong`・1px・角丸10px、フォーカス枠は `ink`、「表示」の文字は `muted`。プレースホルダー `line-strong`・ラベル `ink` は共通）。管理画面の入力欄は `variant="neutral"` を付けて使い回す。公開側の出力は変えない（`AuthComponentsTest` で確認）。
- `<x-auth-input>` は、`label-class` でラベルのクラスを変えられ、スロット `labelExtra` でラベルの右に「任意」を、スロット `hint` でラベルの下の行に補足の文言を出せる（「任意」・補足の要素の id は `describedby` に渡して input の `aria-describedby` につなぐ。登録情報変更ページで使う）。`<x-filled-button>` には 130×34 の `size="xs"` がある。
- `<x-auth-input>` は `type="password"` のとき、入力欄の右端の内側に「表示」「非表示」の切り替えボタンを自動で出す（呼び出し側は変えない。8欄すべて）。Livewire同梱のAlpine.js（`x-data="{ shown: false }"` と `x-bind:type`）で欄ごとに切り替え、初期のHTMLは `type="password"` のまま、Alpine起動前は `x-cloak` でボタンを隠す。Livewireのコンポーネントにはしない（パスワードをサーバーへ送らないため）。ボタンの名前は「表示」「非表示」だけにし、`aria-label` でラベル名を付けない（E2Eなどでラベル名から入力欄を探すとボタンにも当たるため）。`[x-cloak]` のCSSと Alpine は `@livewireStyles` / `@livewireScripts` が出すので、`layouts/auth` でも `layouts/app` と同じく読み込んでいる。
- 購入完了・予約完了のサンクスページの中身は `<x-thanks-page>`（見出しの色 `color-class`、見出しを1行にし始める画面幅 `nowrap`、見出しはスロット `heading`、本文は既定のスロット）。
- 認証画面（ログイン・会員登録・パスワード再設定の2画面）で共通の部品：パネルの案内文と枠線のリンクボタンは `<x-auth-guide :href="route(...)" label="...">案内文</x-auth-guide>`（`@section('guide')` の中身。SPで改行したい位置に `<br class="lg:hidden">`）、フォームの塗りの送信ボタンは `<x-auth-submit>`（画面ごとの余白・幅は `class` で足す。`variant` は primary＝橙の地・白文字（既定）／accent＝緑の地・黒文字、ホバー・フォーカスで濃い緑 `accent-hover`・白文字。管理画面用）。
- `errors/404.blade.php` は404ページ（`layouts/app` を継承。見出しは購入ページと同じ緑・中央寄せ）。存在しないURLも `routes/web.php` の末尾の fallback（`top.not-found`）で web ミドルウェアを通すため、ログイン中はヘッダーがログイン中の表示になる。500・419などのページは作らない。
- 振込先のご案内（`shop/bank_transfer`）・お支払いの確認（`shop/payment_pending`）は、購入ページと同じ白い大きなカード（`shadow-panel`）の中に緑・中央寄せの見出しを置き、金額は `livewire/partials/order-summary` を使う。`bank_transfer` は `PendingOrder` から金額のオブジェクト（`subtotal`・`shippingFee`・`codFee`・`total`）を作って渡す。
- 送料案内の配送料金一覧表（`shipping/fee-list`）は `$areas`（省略すると全地域）と `$compact`（SP用の小さい固定幅の表）を受け取る。PCは全地域の1つの表（`hidden md:block`）、SPは `array_chunk(config('shipping.areas'), 3, true)` の3地域ずつの表4つ（`md:hidden`）を同じビューで出す。金額は `config('shipping.fees')` の「サイズ => [地域名 => 金額]」から地域名で引く。
- 法務系ページ（利用規約・プライバシーポリシー・特商法）は、`.legal-document` の `section` の余白がSP 40px・md以上 80px。見出し（`<x-page-title class="mt-10 md:mt-5">`）の上にSPだけ余白を足す。
- ヘッダーは `layouts.header` が自動で読み込まれ、ナビはルート名で決まる（`.claude/rules/backend.md` を参照）。

```blade
@extends('layouts.app')

@section('body-class', 'bg-cream-100')

@section('content')
    ...
@endsection
```

## CSS

- **Tailwind CSS v4** を使い、Viteでビルドする。入口は `resources/css/app.css` だけで、`layouts/app` の `@vite` で読み込み済み。ページごとのCSSファイルは作らない。
- スタイルはBladeにTailwindのクラスで書く。`app.css` に手書きするのは次の場合だけにする。
  - `@theme`：色・フォント・ヘッダー高さなどのデザイントークン
  - `@utility`：複数の場所で使う小さなスタイルのまとまり（例：リンクの下線の `underline-accent`。太さと距離だけの `underline-thick` は `underline-accent` も使っており、色を別に指定したいとき（管理画面トップのカード内のリンクは `decoration-accent-hover` / `decoration-primary-text`）に単独で使う）
  - `@layer components`：利用規約などの長文の文書のように、子要素にまとめてスタイルを当てる場合（例：`.legal-document`）
- `container` はTailwindのクラス名と重なるため、独自のクラス名として使わない。
- `grid` の子要素の中に横長の表を置く場合は、親に `min-w-0` を付け、表は `overflow-x-auto` で囲んでページ全体が横スクロールしないようにする。
- 管理画面も含め、**全ページにSP（スマホ）向けのスタイルが必要**（生産者がモバイルで管理画面を使うため）。管理画面は高齢の生産者でも使いやすいよう、文字やボタンを大きめにする。

## 画像

- 画像は `.webp` 形式で `storage/app/public/image/{icon,product,tool}` に置き、`asset('storage/image/...')` で参照する。
- 表示には `php artisan storage:link` が必要。
- UIの文言や `alt` は日本語で書く。
