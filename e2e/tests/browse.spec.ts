import { test as guestTest, expect } from '@playwright/test';
import { test } from '../support/fixtures';

// 商品を見る・カート・お問い合わせ・静的ページの流れ（docs/e2e/scenarios.md E-3・E-11・E-12）

// カートは会員だけが使えるので、member（登録・ログイン済み）から始める
test('E-3 トップから商品を探してカートに入れ、数量の変更と削除をする', async ({ page, member: _member }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'おすすめ商品', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'お知らせ', exact: true })).toBeVisible();

    await page.getByRole('banner').getByRole('link', { name: 'オンラインショップ' }).click();
    await expect(page.getByRole('heading', { name: '商品一覧' })).toBeVisible();

    for (const name of ['じゃがいも 2kg', '玉ねぎ 2kg']) {
        await page.goto('/items');
        await page.getByRole('link', { name: `${name}の詳細を見る` }).click();
        await page.getByRole('button', { name: 'カートに入れる' }).click();
        await expect(page.getByRole('dialog')).toBeVisible();
    }
    await page.getByRole('dialog').getByRole('link', { name: 'カートを見る' }).click();

    const potato = page.getByRole('listitem').filter({ hasText: 'じゃがいも 2kg' });
    const onion = page.getByRole('listitem').filter({ hasText: '玉ねぎ 2kg' });
    await expect(potato).toContainText('330円(税込)');
    await expect(page.getByText(/^小計/)).toContainText('685円');

    const quantity = page.getByLabel('じゃがいも 2kg の数量');
    await quantity.fill('3');
    await quantity.blur();
    await expect(potato).toContainText('990円');
    await expect(page.getByText(/^小計/)).toContainText('1,345円');

    await onion.getByRole('button', { name: '削除' }).click();
    await expect(onion).toHaveCount(0);
    await expect(page.getByText(/^小計/)).toContainText('990円');
});

guestTest('E-11 お問い合わせを送る', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'お問い合わせフォームへ' }).click();
    await expect(page.getByRole('heading', { name: 'お問い合わせフォーム' })).toBeVisible();

    // 未入力ではエラーが出て留まる
    await page.getByRole('button', { name: '送信する' }).click();
    await expect(page).toHaveURL(/\/inquiry$/);
    await expect(page.getByLabel('メールアドレス')).toHaveAttribute('aria-invalid', 'true');

    await page.getByLabel('姓').fill('問合');
    await page.getByLabel('名', { exact: true }).fill('花子');
    await page.getByLabel('メールアドレス').fill(`e2e-q-${Date.now().toString(36)}@example.com`);
    await page.getByLabel('お問い合わせ内容').fill('E2Eテストからのお問い合わせです。');
    await page.getByRole('button', { name: '送信する' }).click();

    await expect(page.getByRole('heading', { name: 'Thank you !' })).toBeVisible();
    await page.getByRole('link', { name: 'ホームに戻る' }).click();
    await expect(page.getByRole('heading', { name: 'おすすめ商品', exact: true })).toBeVisible();
});

guestTest('E-12 フッターから静的ページを開く', async ({ page }) => {
    const pages = [
        { link: 'プライバシーポリシー', heading: 'プライバシーポリシー' },
        { link: '特定商取引法に基づく表記', heading: '特定商取引法に基づく表記' },
        { link: '利用規約', heading: '利用規約' },
        { link: '送料案内', heading: '配送料金一覧表' },
    ];
    for (const { link, heading } of pages) {
        await page.goto('/');
        await page.getByRole('contentinfo').getByRole('link', { name: link }).click();
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }
});
