import { test, expect } from '../support/fixtures';
import { waitForMail } from '../support/mailpit';
import { openItem, startPurchase, startReservation } from '../support/shop';
import type { Frame, Page } from '@playwright/test';

// クレジットカード（Stripe Checkout）の流れ（docs/e2e/user.md E-13・E-14）
// Stripe のテストページを実際に操作するため @stripe タグを付け、普段の実行からは外す（./e2e/run.sh stripe で流す）

/**
 * 決済フォームが入っている frame を探す。
 * Stripe Checkout は決済フォームを iframe の中に置き、その中にさらに Apple Pay などの iframe を入れ子にしている。
 * iframe の name や src、入れ子の深さは Stripe が変えることがあるので、それらに頼らず、
 * 「カード番号の入力欄を持つ frame」をページ内の全 frame から探す（入れ子の深さが変わっても見つかる）。
 */
async function findCardFrame(page: Page): Promise<Frame> {
    let found: Frame | undefined;
    await expect
        .poll(
            async () => {
                for (const frame of page.frames()) {
                    if ((await frame.getByRole('textbox', { name: 'カード番号' }).count()) > 0) {
                        found = frame;
                        return true;
                    }
                }
                return false;
            },
            { message: 'Stripe Checkout のカード番号の入力欄が見つかりません', timeout: 30_000 },
        )
        .toBe(true);
    return found!;
}

/** Stripe Checkout のページでテストカードを入力して支払う（表示言語は ja-JP 前提） */
async function payWithTestCard(page: Page): Promise<void> {
    await page.waitForURL(/checkout\.stripe\.com/);
    const form = await findCardFrame(page);
    await form.getByRole('textbox', { name: 'カード番号' }).fill('4242424242424242');
    await form.getByRole('textbox', { name: '有効期限 (月/年)' }).fill('12 / 34');
    await form.getByRole('textbox', { name: 'セキュリティコード' }).fill('123');
    await form.getByRole('textbox', { name: 'カードに記載された名前' }).fill('SHIKEN TARO');
    await form.getByRole('button', { name: '支払う', exact: true }).click();
}

test('E-13 クレジットカードで購入する', { tag: '@stripe' }, async ({ page, member }) => {
    await openItem(page, 'じゃがいも 2kg');
    await startPurchase(page);
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
    await startReservation(page);
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
