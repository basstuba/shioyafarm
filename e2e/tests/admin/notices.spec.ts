import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, goToAdminPage, jstDate, openAdminTop } from '../../support/admin';
import { clickAndWaitForLoad } from '../../support/navigation';

// 管理画面のお知らせの流れ（docs/e2e/admin.md AE-9）

test('AE-9 お知らせを作成・修正・削除し、公開サイトのトップに反映される', async ({ page, publicPage }) => {
    const stamp = Date.now().toString(36);
    const title = `E2Eお知らせ(${stamp})`;
    const text = `E2Eテストのお知らせです。(${stamp})`;
    const editedTitle = `E2Eお知らせ修正後(${stamp})`;
    const editedText = `修正したお知らせの内容です。(${stamp})`;
    const today = jstDate().replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');

    /** 公開サイトのトップのお知らせ欄で、タイトルのお知らせ（なければ空） */
    const publicNotice = (heading: string) => publicPage.getByRole('article').filter({ has: publicPage.getByRole('heading', { name: heading, exact: true }) });

    // 管理画面トップの「お知らせ作成」→ 作成
    await openAdminTop(page);
    await goToAdminPage(page, 'お知らせ作成', /\/admin\/notices\/create$/);
    await expectAdminPage(page, 'お知らせ作成');
    await page.getByLabel('お知らせタイトル').fill(title);
    await page.getByLabel('お知らせ内容').fill(text);
    await page.getByRole('button', { name: '作成する' }).click();

    // 一覧の先頭に出る
    await expect(page).toHaveURL(/\/admin\/notices$/);
    await expect(page.getByText('お知らせを作成しました。')).toBeVisible();
    await expect(page.getByRole('link', { name: /を修正$/ }).first()).toHaveAttribute('aria-label', `${title}を修正`);

    // 公開サイトのトップのお知らせ欄に、作成日つきで出る
    await publicPage.goto('/');
    await expect(publicNotice(title)).toBeVisible();
    await expect(publicNotice(title)).toContainText(text);
    await expect(publicNotice(title).locator('time')).toHaveText(today);

    // 一覧の「修正」→ 修正（入力欄には今の内容が入っている）
    await clickAndWaitForLoad(page.getByRole('link', { name: `${title}を修正` }), /\/admin\/notices\/edit\/\d+$/);
    await expectAdminPage(page, 'お知らせ修正');
    await expect(page.getByLabel('お知らせタイトル')).toHaveValue(title);
    await page.getByLabel('お知らせタイトル').fill(editedTitle);
    await page.getByLabel('お知らせ内容').fill(editedText);
    await page.getByRole('button', { name: '修正する' }).click();
    await expect(page).toHaveURL(/\/admin\/notices$/);
    await expect(page.getByText('お知らせを修正しました。')).toBeVisible();

    // トップには修正した内容が出て、日付は作成日のまま
    await publicPage.goto('/');
    await expect(publicNotice(editedTitle)).toContainText(editedText);
    await expect(publicNotice(editedTitle).locator('time')).toHaveText(today);
    await expect(publicNotice(title)).toHaveCount(0);

    // 一覧の「削除」→ 確認で削除
    await page.getByRole('button', { name: `${editedTitle}を削除` }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText(`「${editedTitle}」を削除します。よろしいですか？`);
    await confirm.getByRole('button', { name: '削除する' }).click();
    await expect(page).toHaveURL(/\/admin\/notices/);
    await expect(page.getByText('お知らせを削除しました。')).toBeVisible();

    // トップから消える
    await publicPage.goto('/');
    await expect(publicPage.getByRole('heading', { name: 'お知らせ', exact: true })).toBeVisible();
    await expect(publicNotice(editedTitle)).toHaveCount(0);
});
