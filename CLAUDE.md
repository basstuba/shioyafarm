# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要

しおや農園（Shioya Farm）は、小規模な家族農家のための直販型ECサイトで、ポートフォリオ用のプロジェクト。公開ショップ（商品・カート・予約・決済。決済はStripeを想定）と、高齢の生産者でも使いやすい管理画面で構成される。管理画面はモバイルでも使う想定のため、全ページにSPデザインが必要。技術スタックは Laravel 11 / PHP 8.2、MySQL 8、Fortify（ユーザー認証、2FA含む）、Livewire 3（動的UIに使用予定）、Tailwind CSS v4 + Vite、maatwebsite/excel（管理画面のエクスポート）。

リポジトリ直下にはDocker構成と `docs/` がある。**Laravelアプリ本体は `src/` にあり**、`php` コンテナと `node` コンテナの `/var/www` にマウントされる。`node` コンテナは起動するとViteの開発サーバー（:5173）を立ち上げる。

## 仕様は `docs/` にある

設計書（日本語。gitignore対象のExcelから変換したもの）が実装内容の正となる。機能を実装する前に確認すること。
- `docs/07-basic-design.md`：ユーザー機能・管理者機能すべてのルート → コントローラー → アクションの対応表と、目標とするURL設計。ほとんどのルートはまだ未実装。
- `docs/05-table-specification.md`、`docs/06-er-diagram.md`：DBスキーマ。
- `docs/02-initial-settings.md`：サイトの文言、商品価格、支払方法の値（`card`、`customer_balance`、`cod`。代金引換は手数料¥330が加算される）。
- `docs/03-page-list.md`、`docs/04-feature-list.md`：ページ一覧と機能一覧。

## コマンド

PHPのコマンドは `php` コンテナ、npmのコマンドは `node` コンテナで実行する（リポジトリ直下から実行）。

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed      # または migrate:fresh --seed
docker compose exec php php artisan storage:link        # 必須：画像は storage/ から配信している
docker compose exec php php artisan test                # 全テスト
docker compose exec php php artisan test --filter=SomeTest
docker compose exec php ./vendor/bin/pint               # コードスタイル
docker compose exec node npm run build                  # 本番用にCSS/JSをビルド（開発中は node コンテナの Vite が自動で反映する）
```

サービス：アプリは http://localhost、phpMyAdmin は :8080、MailHog は :8025。

## アーキテクチャ

レイヤー構成は **Controller → Service → Repository（インターフェース） → Eloquentモデル**。`HomeController` → `HomeService` → `ItemRepository`/`NoticeRepository` が参考実装になる。

詳しい規約は `.claude/rules/` にあり、該当するファイルを触るときに読み込まれる。
- `backend.md`：レイヤー構成、リポジトリのバインド、ルート名とヘッダーナビの関係、モデルの命名、認証
- `frontend.md`：Blade、Tailwind、画像、SP対応
- `shipping.md`：送料計算（`config/shipping.php`）と支払方法

- `testing.md`：テストの書き方（`#[Test]` アトリビュートを使う）
- `uat.md`：UAT（受け入れテスト）のシナリオの書き方と、`docs/uat/` のファイルの書式

設計書のルートを実装するときは `/add-feature` スキルを使う。作業ブランチを切る → 受け入れ条件とUATシナリオ（`docs/uat/`）を作ってユーザーのOKをもらう → `implementer` エージェント（sonnet / effort: high）が実装・テスト → テストが通ってから `uat-tester` エージェントがPlaywrightでUAT → `/code-review` でレビュー → ユーザーがチェックリストで受け入れ確認、の順に進める。`main` ブランチ上で `src/` を編集するとhookでブロックされる。マイグレーションは、`main` にコミット済みのものと、DBに適用済みのものの書き換えがhookでブロックされる（修正用のマイグレーションを追加して直す）。

作業ブランチを `main` にマージする前には、`laravel-verification` スキルで検証する（毎回の実装の流れには含めず、手動で実行する）。phpstan は未導入のため、静的解析の工程は飛ばす。

## 規約

- ユーザーとのやり取り（返答・作業報告・途中経過）はすべて日本語で行う。
- コードコメント、docblock、UIの文言は日本語で書く。
- コミットメッセージはconventionalなプレフィックスに日本語の要約を付ける（例：`feat: ItemRepository,NoticeRepository作成`）。
