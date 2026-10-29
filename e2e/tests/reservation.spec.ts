import { test, expect } from '../support/fixtures';
import { waitForMail } from '../support/mailpit';
import { amount, openItem } from '../support/shop';

// 予約の流れ（docs/e2e/scenarios.md E-6）
// 北の恵みセット 5kg（825円）は予約限定の商品。5kgは80サイズで、北海道の送料は1,230円

test('E-6 予約限定の商品を銀行振込で予約する', async ({ page, member }) => {
    await openItem(page, '北の恵みセット 5kg');
    await expect(page.getByRole('button', { name: 'カートに入れる' })).toHaveCount(0);
    await page.getByRole('link', { name: '予約する' }).click();
    await expect(page.getByRole('heading', { name: 'ご予約内容' })).toBeVisible();

    // 予約は先払いなので、代金引換は選べない
    await expect(page.getByRole('radio')).toHaveCount(2);
    await expect(page.getByRole('radio', { name: /クレジットカード/ })).toBeVisible();
    await expect(page.getByRole('radio', { name: /銀行振込/ })).toBeVisible();

    await page.getByRole('radio', { name: /銀行振込/ }).check();
    await expect(amount(page, '送料')).toHaveText('1,230円');
    await expect(amount(page, '合計')).toHaveText('2,055円');
    await page.getByRole('button', { name: '予約を確定する' }).click();

    await expect(page.getByRole('heading', { name: '振込先のご案内' })).toBeVisible();
    const instructions = page.getByTestId('bank-transfer-instructions');
    await expect(instructions.locator('dt:text-is("振込金額") + dd')).toHaveText('2,055円');
    const bankName = await instructions.locator('dt:text-is("銀行名") + dd').innerText();
    await page.getByRole('button', { name: '振込み' }).click();
    await expect(page.getByRole('heading', { name: /ご予約ありがとうございます/ })).toBeVisible();

    const mail = await waitForMail(member.email, 'ご予約ありがとうございます');
    expect(mail.text).toContain('北の恵みセット 5kg');
    expect(mail.text).toContain(`銀行名：${bankName}`);
    expect(mail.text).toContain('お振込金額：¥2,055');

    await page.goto('/mypage');
    await page.getByRole('button', { name: /^予約日.*の詳細を見る$/ }).click();
    const detail = page.getByRole('dialog', { name: '予約の詳細' });
    await expect(detail.getByText('北の恵みセット 5kg')).toBeVisible();
    await expect(amount(detail, '合計金額')).toHaveText('2,055円');
    await expect(amount(detail, 'お支払い方法')).toHaveText('銀行振込');
});
