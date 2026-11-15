# 案件概要

| 項目 | 内容 |
|---|---|
| 企画・設計開始日 | 2025-07-21 |
| 実装期間 | 2026-10-17 〜 |
| ターゲット | 20歳以上のファミリー層 |

## 開発目的

小規模農家の収益向上を支援するため、生産者の「想い」や「こだわり」を丁寧に伝えられる商品ページと、高齢の生産者でも扱いやすいUI/UXを備えた管理画面を持つ、直販型ECサイトを開発。

## 使用技術

### 言語

- HTML5
- CSS3
- JavaScript（Livewire 3 に同梱の Alpine.js。別途インストールはしない）
- TypeScript（E2Eテストのみ）
- PHP 8.4

### フレームワーク

- Laravel 12

### ツール

- Nginx 1.30.6
- MySQL 8.4.13
- Node.js 24.22.1
- Docker
- phpMyAdmin 5.2.3
- Mailpit 1.31.6
- E2Eテスト用のコンテナ（開発用とは別。php-e2e・nginx-e2e（:8081）・Playwright。DBは専用の `sf_db_e2e`）

### その他

- UIの動的な部分はLivewire 3を使用
- CSSはTailwind CSS v4、ビルドはViteを使用
- 会員の認証はLaravel Fortifyを使用（会員には二段階認証を付けない）
- 管理者のログイン・二段階認証（メールの確認コード）・パスワード再設定は、Fortifyを使わない独自実装（`admin` ガード）
- 決済はStripeを使用（クレジットカードはStripe Checkout、銀行振込はPaymentIntentの `customer_balance`。stripe/stripe-php）
- 管理画面のエクスポートはLaravel Excel（maatwebsite/excel）を使用
- 管理画面でアップロードした商品画像は、PHPのGDで縮小し、WebPにして保存

## 開発ツール

- Larastan（静的解析。レベル5）
- Laravel Pint（コードスタイル）
- PHPUnit 11（テスト。カバレッジ計測は PCOV）
- Playwright Test（E2Eテスト。TypeScript）
- GitHub Actions（`main` への push で Pint・Larastan・テスト・ビルド・依存の脆弱性を確認）
- breezejp（`askdkc/breezejp`。日本語の言語ファイル）

## 追加機能

- 農家さんはモバイルでの管理を望んでいる為、全てSP用デザインを作成する
- 資材管理（発送用化粧箱の在庫及び置き場所の管理）機能
