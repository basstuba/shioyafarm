# E2Eテスト：共通の方針と実行方法

| 項目 | 内容 |
|---|---|
| 対象 | ユーザー画面（公開側・会員）と管理画面 |
| テスト項目 | ユーザー画面は [user.md](user.md)（E-1〜）、管理画面は [admin.md](admin.md)（AE-1〜）。公開側と管理画面にまたがる流れは admin.md に置く |
| ブランチ | test/e2e（1回目）、test/e2e-sp（2回目）、test/e2e-admin（3回目） |
| 根拠 | docs/03-page-list.md、docs/04-feature-list.md、docs/07-basic-design.md、docs/uat/admin/・docs/uat/user/ の各シナリオ |
| 画面幅 | PC（1280×800）と SP（414×896、タッチ操作のスマホとして）。1回目はPCのみ、2回目（フロントエンドの実装後）にSPを追加、3回目に管理画面を追加 |
| 作成日 | 2026-10-29（2回目の追記 2026-11-02、3回目の追記 2026-11-13） |

UATは機能ごとの受け入れ、E2Eは利用者の流れ単位の回帰テスト。細かい入力チェック・境界値はPHPUnitとUATに任せ、ここでは「一連の流れが最後まで通るか」と「流れの中で次の画面に正しく引き継がれるか」を確かめる。

## IDの振り方

- ユーザー画面は `E-<番号>`、管理画面は `AE-<番号>`。テスト名の先頭にIDを付け、`-g E-1` のように一部だけ流せるようにする。
- 項目を消したときも番号は詰めない。

## 共通の方針

- 実行のたびに専用DB（sf_db_e2e）を `migrate:fresh --seed` で作り直す。開発用DBは使わない。PC と SP は同じDBを続けて使うため、テストで作るデータ（会員・商品名・お知らせのタイトルなど）には時刻を入れて、ほかのテストのデータと見分ける。
- メールは Mailpit の API で、宛先を指定して取得し、件名・本文を確かめる。
- 要素は `getByRole` / `getByLabel` / 表示テキストで取り、クラス名やHTMLの構造に頼らない（フロントエンドの実装で壊れにくくする）。
- 回数制限（パスワード再設定はIPごと1分5回、お問い合わせは1分10回、ログインはメールアドレスとIPごとに1分5回など）に当たらないよう、テストは直列で流す。
- ユーザー画面・管理画面に固有の方針は、それぞれ user.md・admin.md に書く。

## 実行方法

リポジトリ直下で次を実行する。E2E用のアプリ（php-e2e・nginx-e2e、:8081）を起動し、CSS/JSをビルドし、専用DBを作り直してから Playwright のコンテナでテストを流す。

```bash
./e2e/run.sh                          # 普段の実行（@stripe 以外。ユーザー画面と管理画面の両方）
./e2e/run.sh stripe                   # クレジットカード（Stripe Checkout）の項目だけ
./e2e/run.sh tests/auth.spec.ts -g E-1   # 一部だけ（playwright test の引数をそのまま渡す）
./e2e/run.sh tests/admin              # 管理画面の項目だけ（最初のログインのsetupも一緒に流れる）
./e2e/run.sh --project='pc*'          # PC・SP の片方だけ（'pc*' または 'sp*'。ユーザー画面と管理画面の両方。指定しなければ全部）
```

- 前提：`src/.env.e2e`（`src/.env` をコピーし、`APP_ENV=e2e`・`APP_URL=http://nginx-e2e`・`DB_DATABASE=sf_db_e2e` に変える。gitignore 対象）と、MySQL の `sf_db_e2e`（sf_user に権限）。
- 管理画面の項目の構成（ログイン済みの状態の使い回し・流す順・回数制限の扱い）は [admin.md](admin.md) の「管理画面に固有の方針」を見る。
- 結果のレポートは `e2e/playwright-report/`、失敗したテストのトレース・スクリーンショットは `e2e/test-results/` に出る。
