# しおや農園（Shioya Farm）

小規模な家族農家のための、直販型の EC サイトです。ポートフォリオとして制作しました。

生産者の想いやこだわりを伝える商品ページと、高齢の生産者でも使いやすい管理画面を目標にしています。管理画面はスマートフォンでも使う想定のため、公開側・管理画面のすべてのページをスマートフォンの画面幅にも対応させています。

## 主な機能

**公開側（お客様）**
- 商品一覧・商品詳細・カート
- 購入と予約（予約限定の商品は予約だけ受け付けます）
- 3種類の支払方法：クレジットカード（Stripe Checkout）、銀行振込（Stripe の PaymentIntent。振込先をご案内）、代金引換（Stripe を通さず、手数料 ¥330 を加算）
- 会員登録・ログイン・パスワード再設定・マイページ（購入・予約の履歴、配送先の追加、登録情報の変更、退会）
- お問い合わせ、お知らせ、送料案内・特定商取引法に基づく表記などの案内ページ
- 購入・予約のサンクスメール、会員登録完了メール

**管理画面**
- 会員・注文・予約・お問い合わせ・お知らせの一覧と検索
- 注文・予約の発送済みのチェックと取消、Excel へのエクスポート
- お問い合わせの対応済みのチェック、退会済みの会員の削除
- 資材（発送用の化粧箱など）の在庫の管理
- お知らせの作成・修正、掲載商品の登録・修正・削除（画像は縮小して WebP に変換）
- 会員・お問い合わせへのメールの作成と送信
- 管理者のログイン（メールの確認コードによる二段階認証）とパスワード再設定

## 技術スタック

| 分類 | 使用技術 |
|---|---|
| バックエンド | PHP 8.4、Laravel 12、Livewire 3、Laravel Fortify（会員の認証） |
| フロントエンド | Blade、Tailwind CSS v4、Vite、Alpine.js（Livewire に同梱のもの） |
| データベース | MySQL 8.4 |
| 決済 | Stripe（stripe/stripe-php） |
| その他のライブラリ | maatwebsite/excel（エクスポート）、GD（商品画像の縮小・WebP への変換） |
| 開発環境 | Docker Compose（Nginx・PHP・Node.js・MySQL・phpMyAdmin・Mailpit） |
| テスト・品質管理 | PHPUnit、Larastan（PHPStan）、Laravel Pint、Playwright（E2E）、GitHub Actions |

管理者のログイン・二段階認証・パスワード再設定は、会員と分けるため Fortify を使わずに実装しています。

## 環境構築

Docker と Docker Compose が必要です。コマンドはリポジトリの直下で実行します。Laravel のアプリ本体は `src/` にあります。

```bash
git clone https://github.com/basstuba/shioyafarm.git
cd shioyafarm
cp src/.env.example src/.env
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan storage:link   # 画像は storage/ から配信しているため必須
```

`node` コンテナは起動すると Vite の開発サーバー（:5173）を立ち上げるので、CSS・JS のビルドは不要です。

`src/.env.example` は、このリポジトリの `compose.yaml` に合わせた値になっています。データベースの名前・ユーザー・パスワードを変える場合は、`compose.yaml` の `MYSQL_DATABASE`・`MYSQL_USER`・`MYSQL_PASSWORD` とそろえてください。

決済を試すには、Stripe の**テストモード**の API キーを `src/.env` の `STRIPE_KEY`・`STRIPE_SECRET` に設定します。

| サービス | URL |
|---|---|
| アプリ（公開側） | http://localhost |
| 管理画面 | http://localhost/admin/login |
| phpMyAdmin | http://localhost:8080 |
| Mailpit（送信したメールの確認） | http://localhost:8025 |

シーダーで、次のアカウントが作られます（ローカル環境で試すためのものです）。

| 種類 | メールアドレス | パスワード |
|---|---|---|
| 会員 | test@test.com | test1234 |
| 管理者 | admin@admin.com | admin1234 |

管理者のログインでは、確認コードのメールが Mailpit に届きます。二段階認証を使わない場合は、`src/.env` に `ADMIN_TWO_FACTOR_ENABLED=false` を設定してください。

画像は同梱していないため、そのままでは表示されません（[画像について](#画像について)）。

## テスト・品質管理

```bash
docker compose exec php php artisan test                 # PHPUnit
docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=1G   # Larastan（レベル5）
docker compose exec php ./vendor/bin/pint --test         # コードスタイル
./e2e/run.sh                                             # E2E（Playwright。専用のDBとコンテナで実行）
./scripts/verify.sh                                      # マージ前の検証（CI と同じ項目＋ルート・Blade のキャッシュの作成）
```

| 項目 | 内容 |
|---|---|
| PHPUnit | 2073件、カバレッジ 98.8%（80%未満で CI が失敗） |
| Larastan | レベル5、エラー0件 |
| E2E | 会員側14項目・管理画面11項目を、PC とスマートフォンの画面幅で実行 |
| CI（GitHub Actions） | `main` への push ごとに Pint・Larastan・PHPUnit・`composer audit`・`npm run build`・`npm audit` を実行 |

E2E の前提と項目は [docs/e2e/](docs/e2e/) にあります。

## 設計で考えたこと

保存する個人情報を増やさないこと、乗っ取られたときの被害を広げないことを優先しました。

- **管理画面から送ったメールの記録**：送信したメールの控えは、農園のアドレスに BCC で送ります。記録は農園のメールボックスに残り、DB にはメールの本文を保存しません。
- **メールの宛先**：メール作成のフォームは、メールアドレスではなく「宛先の種類（会員・お問い合わせ）と ID」だけを送ります。フォームを書き換えても、任意のアドレスには送れません。
- **退会**：退会はまず論理削除にし、取引が終わってから管理者が物理削除します。物理削除のときは、関連する記録やパスワード再設定のトークンも消します。
- **管理者のアカウント**：管理者はシーダーで登録するだけで、画面からの追加・削除は作っていません。使うのは家族数人に限られるため、管理者を作れる入口を増やさないほうが安全で、画面も単純になると判断しました。
- **回数制限**：ログイン・会員登録・パスワード再設定・お問い合わせ・管理者の確認コードの入力に、回数制限をかけています。

## Claude Code での開発

このプロジェクトは、Claude Code（Anthropic の AI コーディングツール）と一緒に開発しました。仕様と方針の決定・受け入れの判断は私が行い、実装・テスト・受け入れテストの操作はサブエージェントに任せています。

1つの機能は、次の流れで進めました。この流れは [`/add-feature` スキル](.claude/skills/add-feature/SKILL.md)にしてあります。

1. 設計書（[docs/](docs/)）から、受け入れ条件と UAT（受け入れテスト）のシナリオを作り、私が確認する
2. [`implementer`](.claude/agents/implementer.md) エージェントが実装し、PHPUnit のテストを書いて通す
3. [`uat-tester`](.claude/agents/uat-tester.md) エージェントが Playwright で画面を操作し、シナリオどおりに動くか確かめる（シナリオは [docs/uat/](docs/uat/)）
4. `/code-review` で差分をレビューする
5. 私がチェックリストで受け入れを確認し、[`scripts/verify.sh`](scripts/verify.sh) を通してからマージする

依頼文での指示だけに頼らず、仕組みで守るようにしています。

- **hook**：[.claude/hooks/](.claude/hooks/) で、`main` ブランチ上でのコードの編集、コミット済みのマイグレーションの書き換え、検証を通っていないブランチのマージ、サブエージェントによる git の書き込みや DB の破壊を止めます。
- **権限の設定**：[.claude/settings.json](.claude/settings.json) で、環境変数のファイルを読ませず、push や DB の作り直しは実行の前に確認を求めます。
- **規約**：[.claude/rules/](.claude/rules/) に、レイヤー構成・フロントエンド・送料・テスト・UAT の書き方の規約を置いています。触るファイルに応じて読み込まれます。
- **設計書と実装の照合**：読み取り専用の [`doc-diff-checker`](.claude/agents/doc-diff-checker.md) エージェントが、設計書と実装のずれを一覧にします。

節目の確認には、外部のスキル集 [Everything Claude Code](https://github.com/affaan-m/everything-claude-code) の `laravel-verification` も使いました（このリポジトリには含めていません）。

体制を作った経緯、うまくいったこと・いかなかったことは、[振り返り](docs/retrospective.md)にまとめています。

## 設計書

| ファイル | 内容 |
|---|---|
| [01-project-overview.md](docs/01-project-overview.md) | 案件の概要（目的・期間・使用技術） |
| [02-initial-settings.md](docs/02-initial-settings.md) | サイトの文言、商品の価格、支払方法 |
| [03-page-list.md](docs/03-page-list.md) | ページ一覧 |
| [04-feature-list.md](docs/04-feature-list.md) | 機能一覧 |
| [05-table-specification.md](docs/05-table-specification.md) | テーブル仕様書 |
| [06-er-diagram.md](docs/06-er-diagram.md) | ER 図 |
| [07-basic-design.md](docs/07-basic-design.md) | 基本設計（ルート・コントローラー・処理の対応、メール、バリデーション） |

## 実装の範囲

ポートフォリオ用のプロジェクトのため、設計書（`docs/`）のうち次の内容は意図して実装していません。

- 商品の販売時期（`docs/02-initial-settings.md`「取扱商品」）による販売・予約の制限。全商品を通年で購入・予約できます。詰め合わせセットの「発送は9月中」は、画面の文言として表示するだけです。

## 画像について

サイトで使用している写真・アイコンは、次のサイトのフリー素材です。配布元の規約で素材の再配布が禁止されているため、このリポジトリには含めていません（ロゴは自作のため含めています）。

- 写真：[写真AC](https://www.photo-ac.com/)、[Pixabay](https://pixabay.com/ja/)
- アイコン：[ICOOON MONO](https://icooon-mono.com/)、[icon-rainbow](https://icon-rainbow.com/)

clone して動かす場合、画像は表示されません。表示するには、次のファイル名で任意の画像（WebP）を置いてください。

- `src/storage/app/public/image/icon/`：`bank-icon.webp`、`box-icon.webp`、`cart-header-icon.webp`、`credit-card-icon.webp`、`id-card-header-icon.webp`、`login-header-icon.webp`、`logout-icon.webp`、`notice-header-icon.webp`、`team-header-icon.webp`、`white-dustbin-icon.webp`
- `src/storage/app/public/image/product/`：`carrots-card.webp`、`onions-card.webp`、`potato-card.webp`、`vegetables-set-card.webp`
- `src/storage/app/public/image/tool/`：`admin-login-page-back-image.webp`、`farmer-introduction-photo.webp`、`fv-agriculture-photo.webp`、`fv-maine-photo.webp`、`fv-onion-photo.webp`、`fv-potatoes-photo.webp`、`inquiry-thanks-page-back-image.webp`、`login-page-back-image.webp`、`my-page-fv-flowers-photo.webp`、`online-fv-family-photo.webp`、`thanks-page-photo.webp`

## 著作権・ライセンス

© 2026 basstuba All rights reserved.

このリポジトリはポートフォリオとして閲覧用に公開しています。コード・文章・ロゴ画像の無断での複製・転載・再利用はお断りします。詳しくは [LICENSE](LICENSE) をご覧ください。
