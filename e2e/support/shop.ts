import { expect, type Locator, type Page } from '@playwright/test';

// ショップの操作（商品詳細を開く・カートに入れる・購入ページの金額を読む）

/** 商品一覧から商品詳細を開く（name は「じゃがいも 2kg」のように商品名と重さ） */
export async function openItem(page: Page, name: string): Promise<void> {
    await page.goto('/items');
    await page.getByRole('link', { name: `${name}の詳細を見る` }).click();
    await expect(page.getByRole('heading', { name })).toBeVisible();
}

/** 商品詳細で「カートに入れる」を押し、モーダルを閉じる */
export async function addToCart(page: Page, name: string): Promise<void> {
    await openItem(page, name);
    await page.getByRole('button', { name: 'カートに入れる' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: '閉じる' }).click();
    await expect(dialog).toBeHidden();
}

/** 金額欄（小計・送料・代引手数料・合計など）の値（dt の文言と一致する項目の、直後の dd） */
export function amount(scope: Page | Locator, label: string): Locator {
    return scope.locator(`dt:text-is("${label}") + dd`);
}

/** 数量の欄を変える（change で Livewire が金額を更新する） */
export async function changeQuantity(page: Page, name: string, quantity: number): Promise<void> {
    const input = page.getByLabel(`${name} の数量`);
    await input.fill(String(quantity));
    await input.blur();
}
