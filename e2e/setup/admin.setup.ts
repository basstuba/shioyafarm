import { test as setup } from '@playwright/test';
import { adminStatePath, isSp } from '../support/device';
import { loginAsAdmin } from '../support/admin';

// 管理者として最初に1回だけログインし、ログイン済みの状態（storageState）を保存する（PC・SP それぞれ。playwright.config.ts）
// このあとの管理画面のテスト（AE-4〜AE-11）は、この状態を使うので、テストごとにログイン（と確認コードのメール）をしない。
// ここが失敗すると、依存している管理画面のテストは「実行されなかった」扱いになり、原因（ログインの失敗）がここに出る。

setup('管理者としてログインし、状態を保存する', async ({ page }) => {
    const device = isSp() ? 'sp' : 'pc';

    // 管理画面トップが開くところまでが loginAsAdmin の中で確かめられる
    await loginAsAdmin(page);
    await page.context().storageState({ path: adminStatePath(device) });
});
