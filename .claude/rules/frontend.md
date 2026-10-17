---
paths:
  - "src/resources/views/**"
  - "src/public/css/**"
---

# フロントエンドの規約

## Figmaのデザイン

すべての画面のデザインはFigmaにあり、実装のたびにユーザーがリンクを渡す。

- PC版とSP版の両方のリンクを使う。SP版のリンクがない場合は推測せず、ユーザーに依頼する。
- Figma MCPで取得したコードは参考情報として扱い、そのまま貼り付けない。
  - Tailwind・React・インラインスタイルは使わず、Blade＋ページ単位のCSSに書き直す。
  - 色・フォント・余白は、`common.css` に既存の定義があればそちらを使う。
  - SP版はPC版のCSSにメディアクエリで追加する。ページごとにSP専用のCSSファイルは作らない。ブレークポイントは `common.css` に合わせて `max-width: 1440px` と `max-width: 567px` を使う。

### 単位（px ではなく em で書く）

`common.css` で `body` の `font-size` を画面幅に応じて変えていて、**`1em` がデザイン上の `1px` に相当する**ようになっている。デザインの基準幅は、PCは1920px（1440px以下では1440px）、SPは414px。

- Figmaの数値（px）は、そのまま `em` に置き換えて書く（例：`font-size: 16px` → `16em`、`padding: 40px` → `40em`）。
- `px` は `border` の太さなど、画面幅で伸び縮みさせたくない場合だけ使う。
- `font-size` を指定した要素の中では `em` の基準が変わる（`font-size: 14em` の要素で `padding: 10em` と書くと140px相当になる）。そのため既存のCSS（`layouts/header.css` など）と同じく、`font-size` はテキストだけの末端の要素（`.nav-item__label` など）に指定し、余白・幅・`gap` はその外側の要素に書く。
- 画像はFigmaから書き出したものを `.webp` に変換し、下の「画像」の規約に従って置く。既存の画像と同じものがあれば、そちらを使う。
- 実装後はFigmaのスクリーンショットと実際の画面を見比べて、違いがあれば報告する。

## Blade

- ビューは `resources/views/{shop,admin,auth,layouts,shipping}` に分かれている。トップページや静的ページは `resources/views` の直下に置く。
- ユーザー画面は `layouts/app`、管理画面は `layouts/admin/app` を継承する。
- `layouts/app` のセクション：
  - `@section('styles')`：ページ専用CSSの `<link>` を書く
  - `@section('body-class')`：`<body>` に付けるクラス
  - `@section('content')`：`<main>` の中身
- ヘッダーは `layouts.header` が自動で読み込まれ、ナビはルート名で決まる（`.claude/rules/backend.md` を参照）。

```blade
@extends('layouts.app')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/shop/cart.css') }}">
@endsection

@section('content')
    ...
@endsection
```

## CSS

- Vite（`resources/css`）は使わず、手書きのプレーンなCSSを `public/css/` に置いて `asset()` で読み込む。
- ビューのパスに合わせて1ページ1ファイルにする（例：`views/shop/cart.blade.php` ↔ `public/css/shop/cart.css`）。
- `reset.css`、`common.css`、`layouts/header.css`、`layouts/footer.css` は `layouts/app` で読み込み済み。共通のスタイルは `common.css` に書く。
- 管理画面も含め、**全ページにSP（スマホ）向けのスタイルが必要**（生産者がモバイルで管理画面を使うため）。管理画面は高齢の生産者でも使いやすいよう、文字やボタンを大きめにする。

## 画像

- 画像は `.webp` 形式で `storage/app/public/image/{icon,product,tool}` に置き、`asset('storage/image/...')` で参照する。
- 表示には `php artisan storage:link` が必要。
- UIの文言や `alt` は日本語で書く。
