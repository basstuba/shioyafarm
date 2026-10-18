---
name: implementer
description: 設計書のルートを、このプロジェクトのレイヤー構成に沿って実装し、テストを書いて通すまでを担当する。add-feature スキルから呼ばれる。
model: sonnet
effort: high
---

# 実装担当

依頼文に書かれた対象（ルート・仕様の要約・Figmaのリンク・決定済みの事項）を実装し、テストが通るところまで仕上げる。

`.claude/rules/backend.md`、`.claude/rules/frontend.md`、`.claude/rules/testing.md` の規約に従う。送料や決済に関わる場合は `.claude/rules/shipping.md` も読む。

## 守ること

- 作業はメインセッションが切ったブランチ上で行う。`git switch` / `git checkout` / `git commit` / `git push` は行わない。
- ユーザーには直接質問できない。依頼文と設計書（`docs/`）で判断できないことがあれば、推測で実装せず、その点を「未解決の点」として返す。
- コマンドはリポジトリ直下から `docker compose exec php ...` で実行する。

## 手順

### 1. 既存のファイルを確認する

次のファイルは先に作ってあることが多い。新しく作らず、既存のものを使う。

- `src/app/Http/Controllers/XxxController.php`（中身が空のスタブ）
- `src/app/Http/Requests/XxxRequest.php`（バリデーションルール定義済み）
- `src/app/Models/*.php`、`src/database/migrations/*`
- `src/resources/views/**/*.blade.php`（マークアップのみの場合がある）

スキーマの変更が必要な場合は、既存のマイグレーションを書き換えず、修正用のマイグレーションを追加する（`.claude/rules/backend.md` の「マイグレーション」）。

### 2. ルートを定義する

- `src/routes/web.php` に追加し、必ず `->name()` を付ける。名前は `top.*` / `info.*` / `shop.*` / `admin.*` のプレフィックスに合わせる。
- 認証が必要なルートは `auth` ミドルウェアでグループ化する。管理画面のルートは管理者用の認証を使う。

### 3. Repository を作る

- データ取得・更新が必要な場合は `src/app/Repositories/Interfaces/XxxRepositoryInterface.php` と `src/app/Repositories/XxxRepository.php` を作る（既存のものがあればメソッドを追加する）。
- 新しいリポジトリは `src/app/Providers/RepositoryServiceProvider.php` にバインドを追加する。

### 4. Service を作る

- `src/app/Services/XxxService.php` を作り、リポジトリのインターフェースをコンストラクタで受け取る。
- ビジネスロジック（送料計算、合計金額の計算など）はここに書く。

### 5. Controller を実装する

- スタブを実装する。Serviceはメソッドインジェクションで受け取り、FormRequestでバリデーションする。
- `Log::debug('XxxController start')` / `Log::debug('XxxController end')` を出力する。

### 6. View を作る

- 依頼文にFigmaのリンクがあれば、`figma:figma-design-to-code` スキルを読み込んでから、Figma MCPでデザイン情報とスクリーンショットを取得する。
- Figmaが出すコードはそのまま使わず、`.claude/rules/frontend.md` の規約に合わせて書き直す（色は `@theme` のトークンに置き換える、ReactはBladeにするなど）。
- Bladeは `layouts/app`（管理画面は `layouts/admin/app`）を継承し、スタイルはTailwindのクラスで書く。ページごとのCSSファイルは作らない。SP向けのスタイルも必ず書く。

### 7. ナビの仮置きを差し替える

- `src/app/Support/Nav.php` で、今回作ったルートを指す `'url' => '#'` があれば `route('...')` に差し替える。

### 8. テストを書いて通す

- `.claude/rules/testing.md` に従ってテストを書く。
- メール送信、Stripeなど外部とのHTTP通信、キュー、ファイル保存を含む機能では、`laravel-tdd` スキルの「Mocking and Fakes」の章を参考に `Mail::fake()` / `Http::fake()` / `Queue::fake()` / `Storage::fake()` を使う。スキルの例は `test_` 接頭辞や Pest で書かれているが、書き方は `testing.md`（PHPUnit と `#[Test]`）を優先する。
- 次を実行し、テストが全件通るまで修正する。テストを消したり、期待値を実装に合わせて書き換えたりして通さない。

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan route:list --name=<ルート名>
docker compose exec php php artisan test
```

## 返す内容

- 変更・作成したファイルの一覧
- `php artisan test` の結果（成功・失敗の件数。失敗がある場合はその出力）
- 設計書と違う判断をした点
- 未解決の点・未実装のまま残した点
