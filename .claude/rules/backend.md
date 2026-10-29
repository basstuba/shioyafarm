---
paths:
  - "src/app/**/*.php"
  - "src/routes/**/*.php"
  - "src/database/**/*.php"
---

# バックエンドの規約

## レイヤー構成

**Controller → Service → Repository（インターフェース） → Eloquentモデル** の順に依存する。参考実装は `HomeController` → `HomeService` → `ItemRepository` / `NoticeRepository`。

- **Controller**：シングルアクション（`__invoke`）か薄く保つ。ロジックは書かず、Serviceをメソッドインジェクションで受け取る。処理の最初と最後に `Log::debug('XxxController start')` / `Log::debug('XxxController end')` を出力する。
- **Service**（`app/Services`）：コンストラクタでリポジトリの**インターフェース**を受け取る。具象クラスやモデルに直接依存しない。
- **Repository**（`app/Repositories`）：`app/Repositories/Interfaces/XxxRepositoryInterface.php` を実装する。実装メソッドには `#[Override]` を付ける。
- **新しいリポジトリは必ず `app/Providers/RepositoryServiceProvider.php` でバインドする。** 忘れると解決時にエラーになる。
- **バリデーション**：FormRequest（`app/Http/Requests`）に書き、`messages()` で日本語のエラーメッセージを定義する。ほとんどのFormRequestは作成済みなので、新しく作る前に既存のものを確認する。
- **Livewire**（`app/Livewire`）：Controller と同じ層として扱う。Service だけに依存し、リポジトリやEloquentの問い合わせを直接書かない（モデルは型やServiceから受け取った値として使うだけ）。Serviceは `mount()` / `render()` / アクションのメソッドインジェクションで受け取る。複数のコンポーネントで共通の処理は `app/Livewire/Concerns` のトレイト（`WithXxx`）にする。
- **Services の値オブジェクトと Store**：`app/Services` には Service のほかに、Service が受け渡す値（`OrderSummary`、`CartLine`、`PendingOrder` など。`final readonly class`）と、セッション・キャッシュへの出し入れだけを持つクラス（`PendingOrderStore`、`OrderTokenStore` のような `*Store`）を置く。DBを使うものはリポジトリにする。外部サービス（Stripe）は `app/Services/Payment` のインターフェース（`PaymentGatewayInterface`）越しに使う。
- **Http/Responses**（`app/Http/Responses`）：Service の結果を、リダイレクト・エラー表示などのHTTPの応答に変える処理を、複数のコントローラーで共有するときに置く（`CheckoutResponder` と、その違いの設定 `CheckoutFlow`）。Fortify の応答を差し替えるクラスもここに置く。
- **結果の返し方**：Service の処理の結果が成功・失敗の2つで収まらないとき（処理中・確認できない・すでに完了など）は、例外や真偽値ではなく `app/Enums` の `*Result` の enum（`CheckoutResult`、`CartAddResult`、`BankTransferCancelResult`）を返し、呼び出し側（Controller / Livewire / CheckoutResponder）が `match` で応答を選ぶ。入力の誤りは `ValidationException` で返す。
- `app/Http/Controllers` の多くは中身が空のスタブ（`throw new \Exception('Not implemented')` のものもある）。新しく作らず、既存のスタブを実装する。
- クラスやメソッドには日本語のdocblockを付ける（例：`/** 商品リポジトリ */`、`/** おすすめ商品を取得 */`）。次のメソッドだけは省略してよい。
  - プロパティ昇格だけのコンストラクタ
  - `#[Override]` を付けた実装（親クラス・インターフェース・トレイトの abstract に説明を書く。補足があるときだけ書く）
  - Livewire の `render()`
  - 親・インターフェース・トレイトのメソッドを実装するときは、docblock の有無にかかわらず `#[Override]` を付ける。

## 型とPHPDoc（PHP 8.4）

型はコードに書き、PHPDocには型宣言で表せないことだけを書く。PHPDocの型は Larastan（`phpstan.neon`）で検査される。

- 引数・戻り値・プロパティには型宣言を必ず書く。型宣言と同じ内容の `@param` / `@return` / `@var` は書かない。
- クラス定数には型を付ける（`private const int NOTICE_LIMIT = 5;`）。
- 依存はコンストラクタのプロパティ昇格で受け取る（`private readonly ItemRepositoryInterface $itemRepository`）。
- PHPDocに書くのは次の場合だけ。
  - コレクション・ページネーターの中身：`@return Collection<int, Item>`、`@return LengthAwarePaginator<int, Item>`
  - リレーション：`@return HasMany<Purchase_history, $this>`、`@return BelongsTo<Item, $this>`
  - 配列の形：`@param array{name: string, price: int} $data`、`@return array<string, string>`
  - 呼び出し側で処理してほしい例外：`@throws`
- Eloquentモデルのカラムは `@property` で書かない（Larastanがマイグレーションから読み取る）。

## ルート名とヘッダーナビ

ヘッダーのナビはルート名で切り替わる。`ViewServiceProvider` が `HeaderComposer` を `layouts.header` に紐づけていて、HeaderComposerは現在のルート名を次のパターンと照合し、`app/Support/Nav.php` のメニューを選ぶ。

| ルート名パターン | 用途 | ナビ |
|---|---|---|
| `top.*` | トップページ・お問い合わせ | ログイン状態で `Nav::topAuth()`（マイページあり・ログアウトなし）/ `Nav::top()` |
| `info.*` | 送料案内・規約などの静的ページ | ログイン状態で `Nav::topAuth()` / `Nav::top()` |
| `shop.mypage.*` | マイページ・登録情報変更ページ | `Nav::mypage()`（「マイページ」なし・ログアウトあり。ログイン必須のためログイン中のナビだけ） |
| `shop.cart`, `shop.checkout.*`, `shop.reservation.*` | カート・購入手続き・予約手続き | `Nav::shopCart()` |
| `shop.*` | ショップ全般 | ログイン状態で `Nav::shopAuth()` / `Nav::shopGuest()` |

- ログイン中は、マイページ以外のすべてのページのヘッダーに「マイページ」を出す。`top.*` / `info.*` はトップ用の `Nav::topAuth()`（ログアウトは出さない）、`shop.*` は `shopAuth()` / `shopCart()` が持っている。マイページ上（`shop.mypage.*`）では `Nav::mypage()` を使い、「マイページ」を出さない。
- SP（768px未満）のヘッダーには、`'sp' => 'bar'` を付けた項目（オンラインショップ・ログイン・カート・マイページなど、よく使うもの）だけを並べ、残りは「メニュー」ボタンで開くパネルに入れる。PCは全項目を横に並べる。振り分けは `Nav::split()` / `Nav::isSpBar()` で行い、Bladeやテストで判定を書き直さない。ナビに項目を追加するときは、SPのヘッダーに出すかどうかを決めて印を付ける（付け忘れるとメニュー側に入る）。SPのヘッダーに出す項目は、幅320pxで1行に収まるよう2つまでにする。
- 新しいルートには必ず `->name()` を付け、上記のプレフィックスに合わせる。どれにも一致しないとナビが空になる。
- `$navMap` は上から順に照合し、最初に一致したものが使われる。パターンを追加するときは、具体的なもの（`shop.cart` など）を、広いもの（`shop.*`）より前に書く。
- `Nav.php` の `'url' => '#'` は、ルートができるまでの仮置き。対応するルートを作ったら、コメントアウトされている `route(...)` に差し替える。

## モデル

- クラス名はテーブル名に合わせてアンダースコア区切りにしている（`Delivery_address`、`Purchase_history`、`Purchase_history_item`）。新しいモデルもこの命名に従う。
- スキーマは `docs/05-table-specification.md` と `docs/06-er-diagram.md` を正とする。

## マイグレーション

- **既存のマイグレーションファイルは書き換えない。** カラムの追加・変更・削除やインデックスの追加は、修正用のマイグレーションを新しく作って行う。
  - 例：`php artisan make:migration add_stock_to_items_table --table=items`
  - ファイル名は `add_<カラム>_to_<テーブル>_table` / `change_<カラム>_in_<テーブル>_table` / `drop_<カラム>_from_<テーブル>_table` のように、何をするかがわかる名前にする。
- **書き換えてよいのは、`main` になく、まだ `migrate` していないファイルだけ。** `main` にコミット済みのもの、DBに適用済み（`migrate:status` が `Ran`）のものは、hookで書き換えがブロックされる。
  - `migrate` する前に、`docs/05-table-specification.md` と見比べて内容を確定させる。
  - 適用した後に誤りに気づいた場合は、修正用のマイグレーションを追加して直す。`migrate:rollback` して書き換えたり、ファイルを削除して作り直したりしない。
- `down()` には `up()` を元に戻す処理を必ず書く。
- カラムの型や属性を変更する場合は `->change()` を使う。変更前の属性（`nullable`、`default`、`comment` など）は引き継がれないので、残したいものはすべて書き直す。
- スキーマを変更したら、`docs/05-table-specification.md` と `docs/06-er-diagram.md` との違いを報告に含める。設計書の更新はユーザーに確認してから、同じ作業ブランチ内で行う（全機能の実装後にまとめて直すのではない）。

## 認証

- ユーザーはFortifyで認証する（`app/Actions/Fortify`、`FortifyServiceProvider`）。2FAのカラムもある。
- 管理者は別の `Admin` モデル・テーブルで、ログインも別（`AdminAuthController`、`auth/admin/login`）。管理画面のルートにユーザー用の認証を流用しない。
