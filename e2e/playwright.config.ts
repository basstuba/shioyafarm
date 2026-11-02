import { defineConfig, devices } from '@playwright/test';

// E2Eテストの設定（docker compose --profile e2e の playwright コンテナで動かす）
export default defineConfig({
    testDir: './tests',
    // 会員を毎回新しく作るのでテスト同士は独立しているが、回数制限（パスワード再設定はIPごと1分5回など）に当たらないよう直列で流す
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
        {
            name: 'pc',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
        },
        // スマホ（414×896、タッチ操作）。ヘッダーのリンクの一部はハンバーガーメニューに入る
        // @stripe の項目は Stripe のページの操作が中心なので、PC だけで流す
        {
            name: 'sp',
            use: { ...devices['Pixel 7'], viewport: { width: 414, height: 896 } },
            grepInvert: /@stripe/,
        },
    ],
});
