import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, goToAdminPage, openAdminTop, waitForAdminReady } from '../../support/admin';
import { waitForMail } from '../../support/mailpit';
import { clickAndWaitForLoad } from '../../support/navigation';

// 管理画面のお問い合わせの流れ（docs/e2e/admin.md AE-7）

test('AE-7 お問い合わせの詳細を見て、対応済みにし、返信メールを送る', async ({ page, publicPage }) => {
    // 一覧で見分けられるよう、姓に時刻を入れる。メールアドレスは30文字以内（お問い合わせの入力ルール）
    const stamp = Date.now().toString(36);
    const lastName = `問合${stamp}`;
    const firstName = '花子';
    const fullName = `${lastName} ${firstName}`;
    const email = `e2e-q-${stamp}@example.com`;
    const detailText = `E2Eテストからのお問い合わせです。(${stamp})`;
    const title = `お問い合わせありがとうございます(${stamp})`;
    const body = `お問い合わせの返信です。(${stamp})`;

    // 公開側でお問い合わせを送る
    await publicPage.goto('/');
    await clickAndWaitForLoad(publicPage.getByRole('link', { name: 'お問い合わせフォームへ' }), /\/inquiry$/);
    await publicPage.getByLabel('姓').fill(lastName);
    await publicPage.getByLabel('名', { exact: true }).fill(firstName);
    await publicPage.getByLabel('メールアドレス').fill(email);
    await publicPage.getByLabel('お問い合わせ内容').fill(detailText);
    await publicPage.getByRole('button', { name: '送信する' }).click();
    await expect(publicPage.getByRole('heading', { name: 'Thank you !' })).toBeVisible();

    // 管理画面のお問い合わせ一覧：先頭に送ったお問い合わせが出て、詳細に内容が出る
    await openAdminTop(page);
    await goToAdminPage(page, 'お問い合わせ一覧', /\/admin\/inquiries$/);
    await expectAdminPage(page, 'お問い合わせ一覧');
    const first = page.getByRole('button', { name: /のお問い合わせの詳細を見る$/ }).first();
    await expect(first).toHaveAttribute('aria-label', `${fullName}のお問い合わせの詳細を見る`);
    await first.click();
    const detail = page.getByRole('dialog', { name: 'お問い合わせの詳細' });
    await expect(detail).toBeVisible();
    await expect(detail.getByText(fullName)).toBeVisible();
    await expect(detail.getByText(email)).toBeVisible();
    await expect(detail.getByText(detailText)).toBeVisible();
    await detail.getByRole('button', { name: '閉じる' }).click();
    await expect(detail).toBeHidden();

    // 対応済みにチェック（押した瞬間に保存される）。再読み込みしても残る
    const addressed = page.getByRole('checkbox', { name: `${fullName}（` });
    await expect(addressed).not.toBeChecked();
    const saved = page.waitForResponse((res) => res.url().endsWith('/admin/inquiries/update') && res.request().method() === 'POST');
    await addressed.check();
    expect((await saved).ok(), '対応済みの保存に失敗').toBe(true);
    await page.reload();
    await waitForAdminReady(page);
    await expect(page.getByRole('checkbox', { name: `${fullName}（` })).toBeChecked();

    // 返信：タイトル・本文を入れて送信 → 確認で送信
    await clickAndWaitForLoad(page.getByRole('link', { name: `${fullName}に返信` }), /\/admin\/mail\/inquiry\/\d+$/);
    await expectAdminPage(page, 'メール作成');
    await expect(page.getByText(email)).toBeVisible();
    await page.getByLabel('メールタイトル').fill(title);
    await page.getByLabel('メール本文').fill(body);
    await page.getByRole('button', { name: '送信する' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('メールを送信します。よろしいですか？');
    await confirm.getByRole('button', { name: '送信する' }).click();

    // お問い合わせ一覧に戻り、案内が出る
    await expect(page).toHaveURL(/\/admin\/inquiries/);
    await expect(page.getByRole('status').filter({ hasText: 'メールを送信しました。' })).toBeVisible();

    // お問い合わせのメールアドレスに、件名「【しおや農園】{タイトル}」のメールが届き、本文の下に元のお問い合わせが引用される
    const mail = await waitForMail(email, `【しおや農園】${title}`);
    expect(mail.subject).toBe(`【しおや農園】${title}`);
    expect(mail.text).toContain(body);
    expect(mail.text).toContain('いただいたお問い合わせ');
    expect(mail.text).toContain(detailText);
});
