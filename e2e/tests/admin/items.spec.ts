import { fileURLToPath } from 'node:url';
import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, goToAdminPage, openAdminTop } from '../../support/admin';
import { clickAndWaitForLoad } from '../../support/navigation';
import { addToCart, openItem } from '../../support/shop';

// 管理画面の掲載商品の流れ（docs/e2e/admin.md AE-10）
// 公開側のカートは会員だけが使えるので、公開側は member（登録・ログイン済み。管理者とは別のコンテキスト）で操作する

// 商品の画像（docs/uat/admin/test-files/ の小さい画像のコピー。UAT のファイルは gitignore 対象なので、E2E用に fixtures/ に置いている）
const IMAGE = fileURLToPath(new URL('../../fixtures/uat-red.png', import.meta.url));

test('AE-10 掲載商品を登録・修正・削除し、公開側の商品一覧・詳細に反映される', async ({ page, publicPage, member: _member }) => {
    const stamp = Date.now().toString(36);
    const name = `E2E商品${stamp}`;
    const label = `${name} 2kg`;

    // 管理画面トップの「掲載商品登録」→ 画像を選んで、通常の商品を登録
    await openAdminTop(page);
    await goToAdminPage(page, '掲載商品登録', /\/admin\/items\/create$/);
    await expectAdminPage(page, '掲載商品登録');
    await page.getByLabel('商品名').fill(name);
    await page.getByRole('radio', { name: '2kg', exact: true }).check();
    await page.getByLabel('商品金額').fill('500');
    await page.getByLabel('商品画像').setInputFiles(IMAGE);
    await page.getByLabel('商品紹介文').fill(`E2Eテストで登録した商品です。(${stamp})`);
    await page.getByRole('radio', { name: 'いいえ', exact: true }).check();
    await page.getByRole('radio', { name: '通常', exact: true }).check();
    await page.getByRole('button', { name: '登録する' }).click();

    // 一覧に案内と商品が出る
    await expect(page).toHaveURL(/\/admin\/items$/);
    await expect(page.getByText('商品を登録しました。')).toBeVisible();
    await expect(page.getByRole('link', { name: `${label}を修正` })).toBeVisible();

    // 公開側の商品一覧・詳細に出て、カートに入れられる
    await publicPage.goto('/items');
    await expect(publicPage.getByRole('link', { name: `${label}の詳細を見る` })).toBeVisible();
    await openItem(publicPage, label);
    await expect(publicPage.getByRole('main')).toContainText('500円(税込)');
    await addToCart(publicPage, label);
    await publicPage.goto('/cart');
    await expect(publicPage.getByRole('listitem').filter({ hasText: label })).toBeVisible();

    // 一覧の「修正」で金額を変える
    await clickAndWaitForLoad(page.getByRole('link', { name: `${label}を修正` }), /\/admin\/items\/edit\/\d+$/);
    await expectAdminPage(page, '掲載商品修正');
    await expect(page.getByLabel('商品名')).toHaveValue(name);
    await page.getByLabel('商品金額').fill('800');
    await page.getByRole('button', { name: '修正する' }).click();
    await expect(page).toHaveURL(/\/admin\/items$/);
    await expect(page.getByText('商品を修正しました。')).toBeVisible();

    // 公開側の商品詳細に新しい金額が出る
    await openItem(publicPage, label);
    await expect(publicPage.getByRole('main')).toContainText('800円(税込)');
    await expect(publicPage.getByRole('main')).not.toContainText('500円(税込)');

    // 一覧の「削除」→ 確認で削除
    await page.getByRole('button', { name: `${label}を削除` }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText(`${label} を削除します。よろしいですか？`);
    await confirm.getByRole('button', { name: '削除する' }).click();
    await expect(page).toHaveURL(/\/admin\/items$/);
    await expect(page.getByText('商品を削除しました。')).toBeVisible();
    await expect(page.getByRole('link', { name: `${label}を修正` })).toHaveCount(0);

    // 公開側の商品一覧から消える
    await publicPage.goto('/items');
    await expect(publicPage.getByRole('heading', { name: '商品一覧' })).toBeVisible();
    await expect(publicPage.getByRole('link', { name: `${label}の詳細を見る` })).toHaveCount(0);
});
