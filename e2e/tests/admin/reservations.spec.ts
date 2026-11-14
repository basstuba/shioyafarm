import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, expectExport, goToAdminPage, openAdminTop, searchList, singleRecordId } from '../../support/admin';
import { searchName } from '../../support/member';
import { reserveByBankTransfer } from '../../support/order';
import { valueOf } from '../../support/shop';

// 管理画面の予約一覧の流れ（docs/e2e/admin.md AE-6）

test('AE-6 予約の詳細を確認してエクスポートし、予約を取り消す', async ({ page, publicPage, member }) => {
    const reservation = await reserveByBankTransfer(publicPage);

    await openAdminTop(page);
    await goToAdminPage(page, '予約一覧', /\/admin\/reservations$/);
    await expectAdminPage(page, '予約一覧');
    await searchList(page, '予約の検索', '予約者名', searchName(member), /\/admin\/reservations\/search/);
    const id = await singleRecordId(page, '予約');

    // 詳細：公開側の予約と同じ内容が出る
    await page.getByRole('button', { name: `予約${id}の詳細を見る` }).click();
    const detail = page.getByRole('dialog', { name: '予約の詳細' });
    await expect(detail).toBeVisible();
    await expect(valueOf(detail, '予約管理番号')).toHaveText(String(id));
    await expect(valueOf(detail, '予約者')).toContainText(member.lastName);
    await expect(detail.getByText(reservation.item)).toBeVisible();
    await expect(valueOf(detail, '送料')).toHaveText(reservation.shipping);
    await expect(valueOf(detail, '合計金額')).toHaveText(reservation.total);
    await expect(valueOf(detail, '配送先氏名')).toHaveText(reservation.deliveryName);
    await expect(valueOf(detail, '配送先住所')).toHaveText(reservation.deliveryAddress);
    await expect(valueOf(detail, '配送先電話番号')).toHaveText(reservation.deliveryTell);
    await expect(valueOf(detail, '支払い方法')).toHaveText('銀行振込');
    await detail.getByRole('button', { name: '閉じる' }).click();
    await expect(detail).toBeHidden();

    // エクスポート（ファイル名まで）。
    // 取り消した予約は、発送状況を「取消済」にして検索しないとエクスポートに入らず、取り消したあとの一覧ではボタンが押せないため、取り消す前に行う
    await expectExport(page, '予約');

    // 取り消す：確認で「取り消す」→ 一覧に案内が出て「取消済」になる
    await page.getByRole('button', { name: `予約${id}の詳細を見る` }).click();
    await expect(detail).toBeVisible();
    await detail.getByRole('button', { name: '取り消す' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toContainText('予約を取り消します。よろしいですか？');
    await confirm.getByRole('button', { name: '取り消す' }).click();

    await expect(page).toHaveURL(/\/admin\/reservations\/search/);
    await expect(page.getByRole('status').filter({ hasText: '予約を取り消しました。' })).toBeVisible();
    await expect(page.locator('span:text-is("取消済")')).toBeVisible();
    await expect(page.getByRole('checkbox', { name: `予約${id}の発送済み` })).toHaveCount(0);

    // 会員のマイページの予約履歴：詳細に「取消済」が出る
    await publicPage.goto('/mypage');
    await publicPage.getByRole('button', { name: /^予約日.*の詳細を見る$/ }).click();
    const history = publicPage.getByRole('dialog', { name: '予約の詳細' });
    await expect(history).toBeVisible();
    await expect(valueOf(history, '状況')).toHaveText('取消済');
});
