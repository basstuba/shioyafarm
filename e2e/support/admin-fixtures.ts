import { test as base, type Page } from '@playwright/test';
import { newSearchableMember, register, type Member } from './member';

// 管理画面と公開側にまたがるテスト用の fixture
// page は管理者としてログイン済み（playwright.config.ts の storageState）。公開側の操作（会員の登録・購入・予約など）は、
// 管理者のセッションと混ざらないよう、別のブラウザコンテキスト（publicPage）で行う。

type Fixtures = {
    /** 公開側用のページ（管理者のログイン状態を持たない別のコンテキスト） */
    publicPage: Page;
    /** 保存したログイン済みの状態（storageState）がまだ有効かの確認（auto。テストごとに最初に行う） */
    adminSessionCheck: void;
    /** publicPage で会員登録してログイン済みの会員（氏名に時刻が入り、管理画面の一覧で検索して見分けられる） */
    member: Member;
};

export const test = base.extend<Fixtures>({
    // 管理画面を開いてログインページへ戻されるなら、テスト本体の「見つからない要素」などの紛らわしい失敗になる前に、原因がわかるメッセージで止める。
    // ページは開かず（context.request でリダイレクトを追わずに）確かめる
    adminSessionCheck: [
        async ({ context }, use) => {
            const res = await context.request.get('/admin', { maxRedirects: 0 });
            if (res.status() >= 300 && res.status() < 400 && /\/admin\/login/.test(res.headers()['location'] ?? '')) {
                throw new Error(
                    'ログイン済みの状態（storageState）が無効で、管理画面がログインページへ戻された。' +
                        'AE-3 が管理者のパスワードを変えた後に *-admin-setup が流れていない可能性がある（playwright.config.ts の project の並び順を確認。*-admin-auth → *-admin-setup → *-admin の順）',
                );
            }
            await use();
        },
        { auto: true },
    ],
    publicPage: async ({ browser }, use) => {
        // browser.newContext() は playwright.config.ts の use（画面幅・ロケールなど）を引き継ぐ。storageState だけは空にする
        const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        await use(await context.newPage());
        await context.close();
    },
    member: async ({ publicPage }, use) => {
        const member = newSearchableMember();
        await register(publicPage, member);
        await use(member);
    },
});

export { expect } from '@playwright/test';
