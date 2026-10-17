---
name: add-feature
description: 設計書（docs/07-basic-design.md）に載っている機能・ページを、このプロジェクトのレイヤー構成（Controller → Service → Repository）に沿って実装する。「〇〇機能を実装して」「/items を作って」など、設計書のルートを実装するときに使う。
argument-hint: "[機能名またはパス 例: 商品一覧 / /items]"
---

# 機能追加の手順

対象：$ARGUMENTS

`.claude/rules/backend.md` と `.claude/rules/frontend.md` の規約に従う。送料や決済に関わる場合は `.claude/rules/shipping.md` も読む。

## 1. 仕様を確認する

- `docs/07-basic-design.md` で対象のパス・HTTPメソッド・コントローラー・アクション・認証の要否を確認する。
- 必要に応じて `docs/03-page-list.md`、`docs/04-feature-list.md`、`docs/05-table-specification.md` も読む。
- 設計書に載っていない、または内容があいまいな場合は、実装する前にユーザーに確認する。

## 2. 既存のファイルを確認する

次のファイルは先に作ってあることが多い。新しく作らず、既存のものを使う。

- `src/app/Http/Controllers/XxxController.php`（中身が空のスタブ）
- `src/app/Http/Requests/XxxRequest.php`（バリデーションルール定義済み）
- `src/app/Models/*.php`、`src/database/migrations/*`
- `src/resources/views/**/*.blade.php`、`src/public/css/**/*.css`（マークアップのみの場合がある）

## 3. ルートを定義する

- `src/routes/web.php` に追加し、必ず `->name()` を付ける。名前は `top.*` / `info.*` / `shop.*` / `admin.*` のプレフィックスに合わせる。
- 認証が必要なルートは `auth` ミドルウェアでグループ化する。管理画面のルートは管理者用の認証を使う。

## 4. Repository を作る

- データ取得・更新が必要な場合は `src/app/Repositories/Interfaces/XxxRepositoryInterface.php` と `src/app/Repositories/XxxRepository.php` を作る（既存のものがあればメソッドを追加する）。
- 新しいリポジトリは `src/app/Providers/RepositoryServiceProvider.php` にバインドを追加する。

## 5. Service を作る

- `src/app/Services/XxxService.php` を作り、リポジトリのインターフェースをコンストラクタで受け取る。
- ビジネスロジック（送料計算、合計金額の計算など）はここに書く。

## 6. Controller を実装する

- スタブを実装する。Serviceはメソッドインジェクションで受け取り、FormRequestでバリデーションする。
- `Log::debug('XxxController start')` / `Log::debug('XxxController end')` を出力する。

## 7. View と CSS を作る

画面のデザインはすべてFigmaにある。

- Figmaのリンクが渡されていない場合は、作業を始める前にユーザーに依頼する。PC版とSP版の両方の、`node-id` 付きのフレーム単位のリンクをもらう。
- `figma:figma-design-to-code` スキルを読み込んでから、Figma MCPでデザイン情報とスクリーンショットを取得する。
- Figmaが出すコード（Tailwind・Reactなど）はそのまま使わず、`.claude/rules/frontend.md` の規約に合わせて書き直す。
- Bladeは `layouts/app`（管理画面は `layouts/admin/app`）を継承し、`@section('styles')` でページ専用のCSSを読み込む。
- CSSはビューのパスに対応する `src/public/css/...` に書く。SP向けのスタイルも必ず書く。

## 8. ナビの仮置きを差し替える

- `src/app/Support/Nav.php` で、今回作ったルートを指す `'url' => '#'` があれば `route('...')` に差し替える。

## 9. 確認する

リポジトリ直下で実行する。

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan route:list --name=<ルート名>
docker compose exec php php artisan test
```

- 画面がある場合は http://localhost で表示を確認する。
- メール送信がある場合は MailHog（http://localhost:8025）で確認する。

## 10. 報告する

実装したファイル、設計書と違う判断をした点、未実装のまま残した点をまとめて報告する。コミットはユーザーに頼まれたときだけ行い、メッセージは `feat: 〇〇機能作成` のように日本語で書く。
