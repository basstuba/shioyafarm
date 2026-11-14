import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, goToAdminPage, openAdminTop, searchList } from '../../support/admin';
import { searchName } from '../../support/member';
import { waitForMail } from '../../support/mailpit';
import { clickAndWaitForLoad } from '../../support/navigation';
import { valueOf } from '../../support/shop';

// 管理画面の会員一覧の流れ（docs/e2e/admin.md AE-8）

test('AE-8 会員にメールを送り、会員が退会したあと削除する', async ({ page, publicPage, member }) => {
    const stamp = Date.now().toString(36);
    const fullName = `${member.lastName} ${member.firstName}`;
    const title = `ご案内(${stamp})`;
    const body = `農園からのご案内です。(${stamp})`;

    // 会員一覧で氏名を検索 →「メール送信」
    await openAdminTop(page);
    await goToAdminPage(page, '会員一覧', /\/admin\/users$/);
    await expectAdminPage(page, '会員一覧');
    await searchList(page, '会員の検索', '氏名', searchName(member), /\/admin\/users\/search/);
    await clickAndWaitForLoad(page.getByRole('link', { name: `${fullName}にメール送信` }), /\/admin\/mail\/user\/\d+$/);

    await expectAdminPage(page, 'メール作成');
    await expect(page.getByText(member.email)).toBeVisible();
    await page.getByLabel('メールタイトル').fill(title);
    await page.getByLabel('メール本文').fill(body);
    await page.getByRole('button', { name: '送信する' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('メールを送信します。よろしいですか？');
    await confirm.getByRole('button', { name: '送信する' }).click();
    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page.getByRole('status').filter({ hasText: 'メールを送信しました。' })).toBeVisible();

    // メールが会員のメールアドレスに届く（会員宛てなので、お問い合わせの引用は付かない）
    const mail = await waitForMail(member.email, `【しおや農園】${title}`);
    expect(mail.to).toContain(member.email);
    expect(mail.text).toContain(body);
    expect(mail.text).not.toContain('いただいたお問い合わせ');

    // 会員が退会する
    await publicPage.goto('/mypage');
    await publicPage.getByRole('button', { name: '退会する' }).click();
    const withdraw = publicPage.getByRole('dialog', { name: '退会の確認' });
    await expect(withdraw).toBeVisible();
    await withdraw.getByRole('button', { name: '退会する' }).click();
    await publicPage.goto('/mypage');
    await expect(publicPage).toHaveURL(/\/login$/);

    // 会員一覧で検索すると、状態が「退会済」になり、詳細に退会日と「削除する」が出る
    await searchList(page, '会員の検索', '氏名', searchName(member), /\/admin\/users\/search/);
    await expect(page.getByRole('cell', { name: /退会済/ })).toBeVisible();
    await page.getByRole('button', { name: `${fullName}の詳細を見る` }).click();
    const detail = page.getByRole('dialog', { name: '会員の詳細' });
    await expect(detail).toBeVisible();
    await expect(valueOf(detail, 'メールアドレス')).toHaveText(member.email);
    await expect(detail.getByText('退会日', { exact: true })).toBeVisible();

    // 削除する → 確認で「削除する」→ 一覧から消える
    await detail.getByRole('button', { name: '削除する' }).click();
    const deleteConfirm = page.getByRole('alertdialog');
    await expect(deleteConfirm).toContainText('削除します。よろしいですか？');
    await deleteConfirm.getByRole('button', { name: '削除する' }).click();
    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page.getByText('会員を削除しました。')).toBeVisible();
    await expect(page.getByText('該当する会員はいません。')).toBeVisible();
});
