import { expect, type Page } from '@playwright/test';
import { openItem, startPurchase, startReservation, valueOf } from './shop';

// 公開側で会員が注文・予約をする手順（管理画面のテストの前準備）
// 購入ページ・予約ページの詳細は E-4〜E-6（purchase.spec.ts・reservation.spec.ts）で確かめているので、ここでは最後まで通すだけにする。

/** 購入ページ・予約ページに出ていた金額・お届け先（管理画面の詳細が「公開側と同じ内容」か比べるために控える） */
export type OrderSummary = {
    /** 商品名と重さ（「じゃがいも 2kg」） */
    item: string;
    /** 送料（「1,230円」） */
    shipping: string;
    /** 代引手数料（代金引換のときだけ。「330円」） */
    codFee: string | null;
    /** 合計（「1,890円」） */
    total: string;
    /** お届け先氏名・住所・電話番号 */
    deliveryName: string;
    deliveryAddress: string;
    deliveryTell: string;
};

async function readSummary(page: Page, item: string): Promise<OrderSummary> {
    const codFee = valueOf(page, '代引手数料');
    return {
        item,
        shipping: await valueOf(page, '送料').innerText(),
        codFee: (await codFee.count()) > 0 ? await codFee.innerText() : null,
        total: await valueOf(page, '合計').innerText(),
        deliveryName: await page.getByLabel('お届け先氏名').inputValue(),
        deliveryAddress: await page.getByTestId('confirm-address').innerText(),
        deliveryTell: await page.getByLabel('お届け先電話番号').inputValue(),
    };
}

/** 商品詳細から代金引換で購入する（会員でログイン済みの page で呼ぶ） */
export async function buyByCashOnDelivery(page: Page, item: string = 'じゃがいも 2kg'): Promise<OrderSummary> {
    await openItem(page, item);
    await startPurchase(page);
    await page.getByRole('radio', { name: /代金引換/ }).check();
    // 代引手数料が加わって合計が変わるのを待ってから読む
    await expect(valueOf(page, '代引手数料')).toHaveText('330円');
    const summary = await readSummary(page, item);
    await page.getByRole('button', { name: '注文を確定する' }).click();
    await expect(page.getByRole('heading', { name: /ご注文ありがとうございました/ })).toBeVisible();
    return summary;
}

/** 商品詳細から銀行振込で購入する（振込先のご案内で「振込み」まで押す） */
export async function buyByBankTransfer(page: Page, item: string = 'じゃがいも 2kg'): Promise<OrderSummary> {
    await openItem(page, item);
    await startPurchase(page);
    await page.getByRole('radio', { name: /銀行振込/ }).check();
    await expect(valueOf(page, '合計')).not.toBeEmpty();
    const summary = await readSummary(page, item);
    await page.getByRole('button', { name: '注文を確定する' }).click();
    await expect(page.getByRole('heading', { name: '振込先のご案内' })).toBeVisible();
    await page.getByRole('button', { name: '振込み' }).click();
    await expect(page.getByRole('heading', { name: /ご注文ありがとうございました/ })).toBeVisible();
    return summary;
}

/** 予約限定の商品を銀行振込で予約する（振込先のご案内で「振込み」まで押す） */
export async function reserveByBankTransfer(page: Page, item: string = '北の恵みセット 5kg'): Promise<OrderSummary> {
    await openItem(page, item);
    await startReservation(page);
    await page.getByRole('radio', { name: /銀行振込/ }).check();
    await expect(valueOf(page, '合計')).not.toBeEmpty();
    const summary = await readSummary(page, item);
    await page.getByRole('button', { name: '予約を確定する' }).click();
    await expect(page.getByRole('heading', { name: '振込先のご案内' })).toBeVisible();
    await page.getByRole('button', { name: '振込み' }).click();
    await expect(page.getByRole('heading', { name: /ご予約ありがとうございます/ })).toBeVisible();
    return summary;
}
