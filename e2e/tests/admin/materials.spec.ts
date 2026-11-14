import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, goToAdminPage, openAdminTop } from '../../support/admin';

// 管理画面の資材の流れ（docs/e2e/admin.md AE-11）

test('AE-11 資材を追加・修正・削除し、在庫が基準値を下回ると「残りわずか」が出る', async ({ page }) => {
    const stamp = Date.now().toString(36);
    const name = `E2E化粧箱${stamp}`;

    await openAdminTop(page);
    await goToAdminPage(page, '資材一覧', /\/admin\/materials$/);
    await expectAdminPage(page, '資材一覧');

    // 「資材を追加」→ 追加（在庫数10・基準値5なので、残りわずかではない）
    await page.getByRole('button', { name: '資材を追加' }).click();
    const form = page.getByRole('dialog', { name: '資材の追加' });
    await expect(form).toBeVisible();
    await form.getByLabel('資材名').fill(name);
    await form.getByLabel('在庫数').fill('10');
    await form.getByLabel('置き場所').fill('E2Eテストの棚');
    await form.getByLabel('基準値').fill('5');
    await form.getByRole('button', { name: '追加する' }).click();

    // 表の最後に出る
    await expect(page).toHaveURL(/\/admin\/materials$/);
    await expect(page.getByText('資材を追加しました。')).toBeVisible();
    await expect(page.getByRole('button', { name: /を修正$/ }).last()).toHaveAttribute('aria-label', `${name}を修正`);
    // 追加した資材の行（ほかの資材の行に「残りわずか」が出ていても影響されないよう、行で絞る）
    const row = page.locator('tr').filter({ has: page.getByRole('button', { name: `${name}を修正` }) });
    await expect(row).toContainText('10');
    await expect(row.getByText('残りわずか')).toHaveCount(0);

    // 「修正」で在庫数を基準値より少なくすると、「残りわずか」が出る
    await page.getByRole('button', { name: `${name}を修正` }).click();
    const edit = page.getByRole('dialog', { name: '資材の修正' });
    await expect(edit).toBeVisible();
    await expect(edit.getByLabel('資材名')).toHaveValue(name);
    await edit.getByLabel('在庫数').fill('3');
    await edit.getByRole('button', { name: '修正する' }).click();
    await expect(page.getByText('資材を修正しました。')).toBeVisible();
    await expect(row.getByText('残りわずか')).toBeVisible();

    // 「削除」→ 確認で削除 → 表から消える
    await page.getByRole('button', { name: `${name}を削除` }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText(`「${name}」を削除します。よろしいですか？`);
    await confirm.getByRole('button', { name: '削除する' }).click();
    await expect(page).toHaveURL(/\/admin\/materials$/);
    await expect(page.getByText('資材を削除しました。')).toBeVisible();
    await expect(page.getByRole('button', { name: `${name}を修正` })).toHaveCount(0);
});

