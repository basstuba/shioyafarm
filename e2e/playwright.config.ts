import { defineConfig, devices } from '@playwright/test';
import { adminStatePath } from './support/device';

const pc = { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } };
// スマホ（414×896、タッチ操作）。ヘッダーのリンクの一部はハンバーガーメニューに入る
const sp = { ...devices['Pixel 7'], viewport: { width: 414, height: 896 } };

// 管理画面のテストの構成（PC・SP それぞれ）
//   *-admin-auth  AE-1〜AE-3（ログイン・ログアウト・パスワード再設定）。ログイン済みの状態を使わず、毎回ログインから始める
//   *-admin-setup 管理者として最初に1回だけログインし、その状態（storageState）を .auth/ に保存する
//   *-admin       AE-4〜AE-11。*-admin-setup の保存した状態を使うので、テストごとにログインしない（ログインの回数制限と二段階認証のメールを減らす）
// AE-3 は管理者のパスワードを変える。パスワードが変わると、変わる前のログイン済みの状態はすべて無効になる（AuthenticateSession）。
// そのため *-admin-auth を、すべての *-admin-setup より先に流す。依存関係（dependencies）を使うと AE-1〜3 の失敗で全体が止まってしまうので、
// 依存はつけず、プロジェクトの定義順（workers: 1 のとき、依存のないプロジェクトは定義順に流れる）で並べる。
// （並びが崩れると、保存した状態が無効になり、*-admin のテストが管理画面を開けずログインページへ戻される。）
// 管理者のログインは、PC・SP あわせて AE-1〜AE-3 で10回、最初のログインで2回の計12回あり、1分5回の回数制限を超える。
// ユーザー画面のテストの間に *-admin-auth を挟んで間隔をあけているが、それでも当たるぶんは support/admin.ts の submitAdminLogin が、
// 画面に出る秒数だけ待って押し直す（最長でおよそ1分。アプリ側の回数制限は変えない）。

// 管理画面のテストは、ログインの回数制限で待つことがある（最長でおよそ1分。support/admin.ts）うえ、公開側の操作（会員登録・購入など）も含むため、長めにする
const adminTimeout = 120_000;

// E2Eテストの設定（docker compose --profile e2e の playwright コンテナで動かす）
export default defineConfig({
    testDir: './tests',
    // 会員を毎回新しく作るのでテスト同士は独立しているが、回数制限（パスワード再設定はIPごと1分5回、管理者のログインは1分5回など）に当たらないよう直列で流す
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: process.env.BASE_URL ?? 'http://nginx-e2e',
        locale: 'ja-JP',
        timezoneId: 'Asia/Tokyo',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        // ユーザー画面（公開側・会員）。管理画面のテスト（tests/admin/）は含めない
        { name: 'pc', use: pc, testIgnore: '**/admin/**' },

        // 管理画面：AE-1〜AE-3（ログイン済みの状態を使わない）
        { name: 'pc-admin-auth', use: pc, testMatch: '**/admin/login.spec.ts', timeout: adminTimeout },

        // @stripe の項目は Stripe のページの操作が中心なので、PC だけで流す
        { name: 'sp', use: sp, testIgnore: '**/admin/**', grepInvert: /@stripe/ },
        { name: 'sp-admin-auth', use: sp, testMatch: '**/admin/login.spec.ts', timeout: adminTimeout },

        // 管理画面：ログイン済みの状態を作る（すべての *-admin-auth より後）
        { name: 'pc-admin-setup', use: pc, testDir: './setup', testMatch: '**/admin.setup.ts', timeout: adminTimeout },
        { name: 'sp-admin-setup', use: sp, testDir: './setup', testMatch: '**/admin.setup.ts', timeout: adminTimeout },

        // 管理画面：AE-4〜AE-11（ログイン済みの状態を使う。*-admin-setup に依存するので、その後に流れる）
        {
            name: 'pc-admin',
            use: { ...pc, storageState: adminStatePath('pc') },
            testMatch: '**/admin/*.spec.ts',
            testIgnore: '**/admin/login.spec.ts',
            dependencies: ['pc-admin-setup'],
            timeout: adminTimeout,
        },
        {
            name: 'sp-admin',
            use: { ...sp, storageState: adminStatePath('sp') },
            testMatch: '**/admin/*.spec.ts',
            testIgnore: '**/admin/login.spec.ts',
            dependencies: ['sp-admin-setup'],
            timeout: adminTimeout,
        },
    ],
});
