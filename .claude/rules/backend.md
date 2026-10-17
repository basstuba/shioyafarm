---
paths:
  - "src/app/**/*.php"
  - "src/routes/**/*.php"
---

# バックエンドの規約

## レイヤー構成

**Controller → Service → Repository（インターフェース） → Eloquentモデル** の順に依存する。参考実装は `HomeController` → `HomeService` → `ItemRepository` / `NoticeRepository`。

- **Controller**：シングルアクション（`__invoke`）か薄く保つ。ロジックは書かず、Serviceをメソッドインジェクションで受け取る。処理の最初と最後に `Log::debug('XxxController start')` / `Log::debug('XxxController end')` を出力する。
- **Service**（`app/Services`）：コンストラクタでリポジトリの**インターフェース**を受け取る。具象クラスやモデルに直接依存しない。
- **Repository**（`app/Repositories`）：`app/Repositories/Interfaces/XxxRepositoryInterface.php` を実装する。実装メソッドには `#[Override]` を付ける。
- **新しいリポジトリは必ず `app/Providers/RepositoryServiceProvider.php` でバインドする。** 忘れると解決時にエラーになる。
- **バリデーション**：FormRequest（`app/Http/Requests`）に書き、`messages()` で日本語のエラーメッセージを定義する。ほとんどのFormRequestは作成済みなので、新しく作る前に既存のものを確認する。
- `app/Http/Controllers` の多くは中身が空のスタブ（`throw new \Exception('Not implemented')` のものもある）。新しく作らず、既存のスタブを実装する。
- クラスやメソッドには日本語のdocblockを付ける（例：`/** 商品リポジトリ */`、`/** おすすめ商品を取得 */`）。

## ルート名とヘッダーナビ

ヘッダーのナビはルート名で切り替わる。`ViewServiceProvider` が `HeaderComposer` を `layouts.header` に紐づけていて、HeaderComposerは現在のルート名を次のパターンと照合し、`app/Support/Nav.php` のメニューを選ぶ。

| ルート名パターン | 用途 | ナビ |
|---|---|---|
| `top.*` | トップページ・お問い合わせ | `Nav::top()` |
| `info.*` | 送料案内・規約などの静的ページ | `Nav::top()` |
| `shop.cart`, `shop.checkout.*` | カート・購入手続き | `Nav::shopCart()` |
| `shop.*` | ショップ全般 | ログイン状態で `Nav::shopAuth()` / `Nav::shopGuest()` |

- 新しいルートには必ず `->name()` を付け、上記のプレフィックスに合わせる。どれにも一致しないとナビが空になる。
- `$navMap` は上から順に照合し、最初に一致したものが使われる。パターンを追加するときは、具体的なもの（`shop.cart` など）を、広いもの（`shop.*`）より前に書く。
- `Nav.php` の `'url' => '#'` は、ルートができるまでの仮置き。対応するルートを作ったら、コメントアウトされている `route(...)` に差し替える。

## モデル

- クラス名はテーブル名に合わせてアンダースコア区切りにしている（`Delivery_address`、`Purchase_history`、`Purchase_history_item`）。新しいモデルもこの命名に従う。
- スキーマは `docs/05-table-specification.md` と `docs/06-er-diagram.md` を正とする。

## 認証

- ユーザーはFortifyで認証する（`app/Actions/Fortify`、`FortifyServiceProvider`）。2FAのカラムもある。
- 管理者は別の `Admin` モデル・テーブルで、ログインも別（`AdminAuthController`、`auth/admin/login`）。管理画面のルートにユーザー用の認証を流用しない。
