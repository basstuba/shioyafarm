import { test, expect } from '@playwright/test';
import { fillLoginForm, fillRegisterForm, logout, newMember, register } from '../support/member';
import { passwordToggle } from '../support/password';
import { waitForMail } from '../support/mailpit';
import { clickHeaderItem, expectHeaderItem } from '../support/header';
import { clickAndWaitForLoad, waitForLoad } from '../support/navigation';
import { openItem, startPurchase } from '../support/shop';

// 会員登録・ログイン・パスワード再設定の流れ（docs/e2e/scenarios.md E-1・E-2・E-7・E-9）

test('E-1 会員登録してログアウトし、もう一度ログインする', async ({ page }) => {
    const member = newMember();

    await page.goto('/items');
    await clickHeaderItem(page, 'ログイン');
    await clickAndWaitForLoad(page.getByRole('link', { name: '会員登録' }), /\/register$/);

    // パスワードの表示切替：「表示」で入力した文字が見え（確認用パスワードは伏せ字のまま）、「非表示」で伏せ字に戻る
    const password = page.getByLabel('パスワード', { exact: true });
    const confirmation = page.getByLabel('確認用パスワード');
    await password.fill(member.password);
    await confirmation.fill(member.password);
    await expect(password).toHaveAttribute('type', 'password');
    await (await passwordToggle(password, '表示')).click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(password).toHaveValue(member.password);
    await expect(confirmation).toHaveAttribute('type', 'password');
    await expect(await passwordToggle(confirmation, '表示')).toBeVisible();
    await expect(await passwordToggle(password, '表示')).toHaveCount(0);
    await (await passwordToggle(password, '非表示')).click();
    await expect(password).toHaveAttribute('type', 'password');
    await expect(await passwordToggle(password, '表示')).toBeVisible();

    await fillRegisterForm(page, member);

    // 会員登録の前に見ていたページ（商品一覧）へ戻り、ログイン中のナビになる
    await expect(page).toHaveURL(/\/items$/);
    await expectHeaderItem(page, 'マイページ', 'link', true);
    await expectHeaderItem(page, 'ログアウト', 'button', true);

    await logout(page);
    await page.goto('/items');
    await expectHeaderItem(page, 'ログイン', 'link', true);
    await expectHeaderItem(page, 'マイページ', 'link', false);

    // ログイン後も、ログインの前に見ていたページへ戻る。パスワードを「表示」にしたままでもログインできる
    await clickHeaderItem(page, 'ログイン');
    await waitForLoad(page, /\/login$/);
    await fillLoginForm(page, member.email, member.password, { showPassword: true });
    await expect(page).toHaveURL(/\/items$/);
    await expectHeaderItem(page, 'マイページ', 'link', true);
});

test('E-2 誤ったパスワードではログインできない', async ({ page }) => {
    const member = newMember();
    await register(page, member);
    await logout(page);

    await page.goto('/login');
    await fillLoginForm(page, member.email, 'wrong-password');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible();

    // ログイン状態になっていない
    await page.goto('/mypage');
    await expect(page).toHaveURL(/\/login$/);
});

test('E-7 未ログインで商品詳細を開き、会員登録すると購入できるようになる', async ({ page }) => {
    const member = newMember();

    await openItem(page, 'じゃがいも 2kg');
    const itemUrl = page.url();

    // 未ログインでは購入ボタンの代わりに会員登録の案内が出る
    await expect(page.getByText('※ご購入の際は会員登録とログインをお願いします。')).toBeVisible();
    await expect(page.getByRole('link', { name: '購入する' })).toHaveCount(0);

    await clickAndWaitForLoad(page.getByRole('main').getByRole('link', { name: '会員登録' }), /\/register$/);
    await fillRegisterForm(page, member);

    // 会員登録の前に見ていた商品詳細へ戻り、購入できる
    await expect(page).toHaveURL(itemUrl);
    await startPurchase(page);
    await expect(page.getByRole('heading', { name: 'ご注文内容' })).toBeVisible();
    await expect(page.getByLabel('お届け先氏名')).toHaveValue(`${member.lastName}${member.firstName}`);
});

test('E-9 パスワードを再設定し、新しいパスワードでログインする', async ({ page }) => {
    const member = newMember();
    const newPassword = 'e2enewpass456';
    await register(page, member);
    await logout(page);

    await page.goto('/login');
    await page.getByRole('link', { name: 'パスワードをお忘れの方' }).click();
    await page.getByLabel('メールアドレス').fill(member.email);
    await page.getByRole('button', { name: '再設定メールを送信' }).click();
    await expect(page.getByRole('status')).toHaveText('ご登録のメールアドレス宛てに、パスワード再設定用のメールをお送りしました。');

    const mail = await waitForMail(member.email, 'パスワード再設定のご案内');
    const resetUrl = mail.text.match(/https?:\/\/[^\s)<>"']+\/reset-password\/[^\s)<>"']+/)?.[0];
    expect(resetUrl, '再設定メールにリンクがない').toBeTruthy();

    await page.goto(resetUrl!);
    await expect(page.getByRole('heading', { name: '新しいパスワードの設定' })).toBeVisible();
    await page.getByLabel('新しいパスワード').fill(newPassword);
    await page.getByLabel('確認用パスワード').fill(newPassword);
    await page.getByRole('button', { name: 'パスワードを再設定する' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('status')).toHaveText('パスワードを再設定しました。新しいパスワードでログインしてください。');

    // 古いパスワードではログインできず、新しいパスワードではできる
    await fillLoginForm(page, member.email, member.password);
    await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible();
    await fillLoginForm(page, member.email, newPassword);
    await expect(page).not.toHaveURL(/\/login$/);
    await expectHeaderItem(page, 'マイページ', 'link', true);

    // 使用済みのリンクは無効（トップのナビにはログアウトがないので、商品一覧でログアウトする）
    await page.goto('/items');
    await logout(page);
    // （リンクを開くとフォームは出るが、送信すると無効の案内になる。docs/uat/password-reset.md S-11）
    await page.goto(resetUrl!);
    await page.getByLabel('新しいパスワード').fill('e2eagain789');
    await page.getByLabel('確認用パスワード').fill('e2eagain789');
    await page.getByRole('button', { name: 'パスワードを再設定する' }).click();
    await expect(page.getByText('このリンクは無効か、有効期限が切れています。もう一度再設定メールを送信してください。')).toBeVisible();
    await expect(page.getByRole('link', { name: '再設定メールを送信する' })).toBeVisible();
});
