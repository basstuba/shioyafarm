import { test, expect } from '../support/fixtures';
import { waitForMail } from '../support/mailpit';
import { openItem, startPurchase, startReservation } from '../support/shop';
import type { Frame, Page } from '@playwright/test';

// クレジットカード（Stripe Checkout）の流れ（docs/e2e/user.md E-13・E-14）
// Stripe のテストページを実際に操作するため @stripe タグを付け、普段の実行からは外す（./e2e/run.sh stripe で流す）

/**
 * ページ本体と全 frame（入れ子の iframe を含む）から、条件に合う frame を探す。
 * Stripe Checkout は決済フォームを iframe の中に置き、その中にさらに Apple Pay などの iframe を入れ子にしている。
 * iframe の name や src、入れ子の深さは Stripe が変えることがあるので、それらに頼らず、中身で探す。
 * 走査の途中で frame が外れる（detach される）と調べる処理が reject するので、その frame は「見つからない」として扱い、探し続ける。
 */
async function findFrame(page: Page, matches: (frame: Frame) => Promise<boolean>, message: string): Promise<Frame> {
    let found: Frame | undefined;
    await expect
        .poll(
            async () => {
                for (const frame of page.frames()) {
                    try {
                        if (await matches(frame)) {
                            found = frame;
                            return true;
                        }
                    } catch {
                        // 走査中に frame が外れた。次の frame へ
                    }
                }
                return false;
            },
            { message, timeout: 30_000 },
        )
        .toBe(true);
    return found!;
}

/** Stripe Checkout のページが開くまで待つ */
async function waitForCheckout(page: Page): Promise<void> {
    await page.waitForURL(/checkout\.stripe\.com/);
}

/**
 * Stripe Checkout の画面に「しおや農園 に支払う」と出て、「〜に支払う」の直前が Stripe アカウントのメールアドレスでないことを確かめる。
 * 店名だけの部分一致だと商品名などでも通るため、「に支払う」の文言まで含めて確かめる（空白や改行の揺れは \s* で吸収する）。
 * 表示がページ本体か iframe かは問わず、本体と全 frame のどれかに出ればよい。PC・SP のどちらの幅でも同じ文言が出る
 * （SP のヘッダーの店名は省略されるが、「〜に支払う」の行は全文が出る）。
 */
async function expectShopNameOnCheckout(page: Page): Promise<void> {
    const frame = await findFrame(
        page,
        async (f) => (await f.getByText(/しおや農園\s*に支払う/).count()) > 0,
        'Stripe Checkout の画面に「しおや農園 に支払う」が出ていません',
    );
    await expect(frame.getByText(/@[^\s]+\s*に支払う/)).toHaveCount(0);
}

/** Stripe Checkout のページでテストカードを入力して支払う（表示言語は ja-JP 前提） */
async function payWithTestCard(page: Page): Promise<void> {
    const form = await findFrame(
        page,
        async (f) => (await f.getByRole('textbox', { name: 'カード番号' }).count()) > 0,
        'Stripe Checkout のカード番号の入力欄が見つかりません',
    );
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

    await waitForCheckout(page);
    await expectShopNameOnCheckout(page);
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

    await waitForCheckout(page);
    await expectShopNameOnCheckout(page);
    await payWithTestCard(page);
    await expect(page.getByRole('heading', { name: /ご予約ありがとうございます/ })).toBeVisible({ timeout: 30_000 });

    const mail = await waitForMail(member.email, 'ご予約ありがとうございます');
    expect(mail.text).toContain('お支払方法：クレジットカード');
    expect(mail.text).toContain('北の恵みセット 5kg');

    await page.goto('/mypage');
    await expect(page.getByRole('button', { name: /^予約日.*の詳細を見る$/ })).toHaveCount(1);
});
