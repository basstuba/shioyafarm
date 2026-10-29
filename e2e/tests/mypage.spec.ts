import { test, expect } from '../support/fixtures';
import { fillLoginForm } from '../support/member';
import { openItem } from '../support/shop';

// マイページの流れ（docs/e2e/scenarios.md E-8・E-10）

test('E-8 登録情報を変更すると、マイページと購入ページの初期値に反映される', async ({ page, member }) => {
    await page.goto('/mypage');
    await page.getByRole('link', { name: '登録情報を変更する' }).click();

    // 何も変えずに保存すると、保存せずに留まる
    await page.getByRole('button', { name: '変更する' }).click();
    await expect(page).toHaveURL(/\/mypage\/edit$/);
    await expect(page.getByRole('status')).toHaveText('変更された項目はありません');

    await page.getByLabel('姓').fill('変更');
    await page.getByLabel('お住まいの地域').selectOption('東京都');
    await page.getByLabel('ご住所').fill('港区E2E3-3');
    await page.getByRole('button', { name: '変更する' }).click();

    await expect(page).toHaveURL(/\/mypage$/);
    await expect(page.getByText(`変更 ${member.firstName}`).or(page.getByText(`変更${member.firstName}`)).first()).toBeVisible();
    await expect(page.getByText(/港区E2E3-3/).first()).toBeVisible();

    await openItem(page, 'じゃがいも 2kg');
    await page.getByRole('link', { name: '購入する' }).click();
    await expect(page.getByLabel('お届け先氏名')).toHaveValue(`変更${member.firstName}`);
    await expect(page.getByTestId('confirm-address')).toHaveText('東京都港区E2E3-3');
});

test('E-10 退会すると、同じメールアドレスでログインできなくなる', async ({ page, member }) => {
    await page.goto('/mypage');
    await page.getByRole('button', { name: '退会する' }).click();
    const dialog = page.getByRole('dialog', { name: '退会の確認' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: '退会する' }).click();

    // 退会後はログアウト状態
    await page.goto('/mypage');
    await expect(page).toHaveURL(/\/login$/);

    await fillLoginForm(page, member.email, member.password);
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible();
});
