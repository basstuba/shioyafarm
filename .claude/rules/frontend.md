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

SP版のリンクがない場合は、実装前にユーザーと相談してFigmaにSP版のフレームを作る。推測で実装しない。

1. PC版のデザインを読み込み、SP版のレイアウト方針（縦積みにする部分、ナビの形、表をカード形式にするかなど）を短く提案する。
2. `figma:figma-use` と `figma:figma-generate-design` スキルを読み込み、Figma MCPで、PC版と同じファイルにSP版のフレーム（幅414px）を作る。色・フォント・コンポーネントはPC版のものを使う。
3. 作ったフレームのリンクとスクリーンショットをユーザーに見せ、OKが出るまで修正する。管理画面は高齢の生産者向けに、文字やボタンを大きめにする。
4. OKが出たら、そのフレームをSP版のデザインとして実装する。

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
- 何度も出てくる部品はBladeコンポーネント（`resources/views/components/`）にする。ページの見出しは `<x-page-title>` を使う（`layouts/auth` の見出しと、お問い合わせフォームのカード内の見出しはデザインが違うため除く）。
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
