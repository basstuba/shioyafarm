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
- ユーザー画面は `layouts/app`、管理画面は `layouts/admin/app`、認証画面（ログイン・会員登録）は `layouts/auth`、サンクスページ（お問い合わせ・購入・予約の完了）は `layouts/thanks` を継承する。
- `<head>`（メタ・フォント・favicon・`@vite`）は `layouts/head` にまとめてあり、各レイアウトから `@include` する。レイアウトに直接書かない。`<title>` は `@section('page-title')` で変えられる（省略時は「Shioya Farm」）。
- `layouts/app` のセクション：
  - `@section('body-class')`：`<body>` に付けるクラス（背景色の `bg-cream-100` や、フッターの色を変える目印の `legal-page` など）
  - `@section('content')`：`<main>` の中身
- `layouts/thanks` は、共通ヘッダー・フッターを表示せず、`<main>` の中身だけを出す（Figmaのサンクスページにヘッダー・フッターがないため）。セクションは `page-title`・`body-class`・`content`。
- `layouts/auth` は、共通ヘッダー・フッター（`layouts.header` / `layouts.footer`）を使わず、認証ページ用のヘッダー（ロゴ）と © を持つ。PC（`lg:` 以上）は左にフォーム、右に背景画像のパネルの2分割、SPは画面全体に背景画像を敷いて縦に並べる。セクション：
  - `@section('form')`：左側（SPでは見出しの下）のフォーム
  - `@section('heading')`：右側のパネル（SPでは上部）の見出し
  - `@section('guide')`：見出しの下の案内文とリンクボタン（ログインページなら会員登録への案内）
  - 入力欄は `<x-auth-input>`（ラベル・input・エラーメッセージをまとめたもの）を使う。
- 何度も出てくる部品はBladeコンポーネント（`resources/views/components/`）にする。ページの見出しは `<x-page-title>` を使う（`layouts/auth` の見出し、お問い合わせフォームのカード内の見出し、商品詳細の商品名（カードの中で画像の横に置く）、カートの見出し（緑・32px・左寄せ）、購入ページの見出し（緑・32px・中央寄せ）、マイページのセクション見出し（緑・24px・中央寄せ。`<x-section-title>`）、登録情報変更ページの見出し（カードの中の緑・32px・中央寄せの h1）はデザインが違うため除く）。
- ボタンは `<x-filled-button>`（橙の塗り）と `<x-outline-button>`（白地に橙の枠線）で、どちらもクラスベースのコンポーネント（`app/View/Components/FilledButton.php`・`OutlineButton.php`）。props は `FilledButton` が `href`・`type`・`size`・`variant`、`OutlineButton` が `href`・`type`・`size`。props の説明と、大きさ・色・影・disabled のクラスの組み立て（`classes()`・`disabledClasses()`）はクラス側にあり、Blade は `<a>`／`<button>` の出し分けと `$attributes->class(...)` だけを書く。大きさや色を足したら、影や既定値などの分岐が変わる場合だけ `tests/Unit` のクラスのテストを直す（クラスの文字列は `tests/Feature` の `Blade::render` のテストで確認する）。
- 入力欄の Blade 部品（`form-field`・`auth-input`・`auth-select`）が `old()` の値を取り出すときは、`App\Support\OldInput::scalar($name, $default)` を使う（配列など文字列にできない値は空文字にする）。部品ごとに `is_scalar` の判定を書かない。Livewire 側のフォーム（`WithDeliveryForm`・`PurchaseForm`）の old の復元は trim などの別の処理もあるため対象外。
- 選択欄（`<select>`）は、Figmaの茶色の三角の矢印が付いた `<x-select>` を使う（`auth-select`・お届け先住所・住所追加モーダルの都道府県で共通）。エラー文には id を付け、`aria-describedby` でつなぐ。
- 購入ページと予約ページで共通の部品：商品の行は `<x-checkout-line>`（数量の input の属性・`wire:change` の式・エラー文のスロットを渡す。列の幅は予約ページ＝860pxにそろえてある）、金額のカードは `livewire/partials/order-summary`（`$summary` と上の余白 `$topClass` を渡して `@include`）。
- マイページ専用の部品：セクション見出しは `<x-section-title>`（h2）。退会手続きの小さなボタン（130×30、SPは高さ44）は `<x-small-button>`（`variant` は danger＝「退会する」／cancel＝「キャンセル」）。履歴の表（`livewire/partials/history-table`）・ページ送り（`livewire/partials/pager`。`$topClass` で上の余白を渡す）・詳細モーダル（`livewire/partials/order-detail-modal`）は購入履歴と予約履歴で共通。
- `<x-auth-input>` は、`label-class` でラベルのクラスを変えられ、スロット `labelExtra` でラベルの右に「任意」を、スロット `hint` でラベルの下の行に補足の文言を出せる（「任意」・補足の要素の id は `describedby` に渡して input の `aria-describedby` につなぐ。登録情報変更ページで使う）。`<x-filled-button>` には 130×34 の `size="xs"` がある。
- `<x-auth-input>` は `type="password"` のとき、入力欄の右端の内側に「表示」「非表示」の切り替えボタンを自動で出す（呼び出し側は変えない。8欄すべて）。Livewire同梱のAlpine.js（`x-data="{ shown: false }"` と `x-bind:type`）で欄ごとに切り替え、初期のHTMLは `type="password"` のまま、Alpine起動前は `x-cloak` でボタンを隠す。Livewireのコンポーネントにはしない（パスワードをサーバーへ送らないため）。ボタンの名前は「表示」「非表示」だけにし、`aria-label` でラベル名を付けない（E2Eなどでラベル名から入力欄を探すとボタンにも当たるため）。`[x-cloak]` のCSSと Alpine は `@livewireStyles` / `@livewireScripts` が出すので、`layouts/auth` でも `layouts/app` と同じく読み込んでいる。
- 購入完了・予約完了のサンクスページの中身は `<x-thanks-page>`（見出しの色 `color-class`、見出しを1行にし始める画面幅 `nowrap`、見出しはスロット `heading`、本文は既定のスロット）。
- 認証画面（ログイン・会員登録・パスワード再設定の2画面）で共通の部品：パネルの案内文と枠線のリンクボタンは `<x-auth-guide :href="route(...)" label="...">案内文</x-auth-guide>`（`@section('guide')` の中身。SPで改行したい位置に `<br class="lg:hidden">`）、フォームの塗りの送信ボタンは `<x-auth-submit>`（画面ごとの余白・幅は `class` で足す）。
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
  - `@utility`：複数の場所で使う小さなスタイルのまとまり（例：リンクの下線の `underline-accent`）
  - `@layer components`：利用規約などの長文の文書のように、子要素にまとめてスタイルを当てる場合（例：`.legal-document`）
- `container` はTailwindのクラス名と重なるため、独自のクラス名として使わない。
- `grid` の子要素の中に横長の表を置く場合は、親に `min-w-0` を付け、表は `overflow-x-auto` で囲んでページ全体が横スクロールしないようにする。
- 管理画面も含め、**全ページにSP（スマホ）向けのスタイルが必要**（生産者がモバイルで管理画面を使うため）。管理画面は高齢の生産者でも使いやすいよう、文字やボタンを大きめにする。

## 画像

- 画像は `.webp` 形式で `storage/app/public/image/{icon,product,tool}` に置き、`asset('storage/image/...')` で参照する。
- 表示には `php artisan storage:link` が必要。
- UIの文言や `alt` は日本語で書く。
