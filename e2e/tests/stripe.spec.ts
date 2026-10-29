import { test, expect } from '../support/fixtures';
import { waitForMail } from '../support/mailpit';
import { openItem } from '../support/shop';
import type { Page } from '@playwright/test';

// クレジットカード（Stripe Checkout）の流れ（docs/e2e/scenarios.md E-13・E-14）
// Stripe のテストページを実際に操作するため @stripe タグを付け、普段の実行からは外す（./e2e/run.sh stripe で流す）

/** Stripe Checkout のページでテストカードを入力して支払う */
async function payWithTestCard(page: Page): Promise<void> {
    await page.waitForURL(/checkout\.stripe\.com/);
    await page.locator('#cardNumber').fill('4242424242424242');
    await page.locator('#cardExpiry').fill('12 / 34');
    await page.locator('#cardCvc').fill('123');
    await page.locator('#billingName').fill('SHIKEN TARO');
    await page.getByTestId('hosted-payment-submit-button').click();
}

test('E-13 クレジットカードで購入する', { tag: '@stripe' }, async ({ page, member }) => {
    await openItem(page, 'じゃがいも 2kg');
    await page.getByRole('link', { name: '購入する' }).click();
    await page.getByRole('radio', { name: /クレジットカード/ }).check();
    await page.getByRole('button', { name: '注文を確定する' }).click();

    await payWithTestCard(page);
    await expect(page.getByRole('heading', { name: /ご注文ありがとうございました/ })).toBeVisible({ timeout: 30_000 });

    const mail = await waitForMail(member.email, 'ご注文ありがとうございます');
    expect(mail.text).toContain('お支払方法：クレジットカード');
    expect(mail.text).toContain('じゃがいも 2kg × 1');

    await page.goto('/mypage');
    await expect(page.getByRole('button', { name: /^注文日.*の詳細を見る$/ })).toHaveCount(1);
});

test('E-14 クレジットカードで予約する', { tag: '@stripe' }, async ({ page, member }) => {
    await openItem(page, '北の恵みセット 5kg');
    await page.getByRole('link', { name: '予約する' }).click();
    await page.getByRole('radio', { name: /クレジットカード/ }).check();
    await page.getByRole('button', { name: '予約を確定する' }).click();

    await payWithTestCard(page);
    await expect(page.getByRole('heading', { name: /ご予約ありがとうございます/ })).toBeVisible({ timeout: 30_000 });

    const mail = await waitForMail(member.email, 'ご予約ありがとうございます');
    expect(mail.text).toContain('お支払方法：クレジットカード');
    expect(mail.text).toContain('北の恵みセット 5kg');

    await page.goto('/mypage');
    await expect(page.getByRole('button', { name: /^予約日.*の詳細を見る$/ })).toHaveCount(1);
});
