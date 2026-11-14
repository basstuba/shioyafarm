import { test, expect } from '../../support/admin-fixtures';
import { expectAdminPage, expectExport, goToAdminPage, openAdminTop, searchList, singleRecordId, waitForAdminReady } from '../../support/admin';
import { searchName } from '../../support/member';
import { buyByBankTransfer, buyByCashOnDelivery } from '../../support/order';
import { valueOf } from '../../support/shop';

// 管理画面の注文一覧の流れ（docs/e2e/admin.md AE-4・AE-5）
// 会員の注文は公開側（publicPage。管理者とは別のコンテキスト）で行う。注文は代金引換・銀行振込（カードは @stripe になるので使わない）

test('AE-4 注文の詳細を確認し、発送済みにして、エクスポートする', async ({ page, publicPage, member }) => {
    const order = await buyByCashOnDelivery(publicPage);

    // 管理画面トップ → 注文一覧 → 注文者名で検索
    await openAdminTop(page);
    await goToAdminPage(page, '注文一覧', /\/admin\/purchase$/);
    await expectAdminPage(page, '注文一覧');
    await searchList(page, '注文の検索', '注文者名', searchName(member), /\/admin\/purchase\/search/);
    const id = await singleRecordId(page, '注文');

    // 詳細：公開側の注文と同じ内容が出る
    await page.getByRole('button', { name: `注文${id}の詳細を見る` }).click();
    const detail = page.getByRole('dialog', { name: '注文の詳細' });
    await expect(detail).toBeVisible();
    await expect(valueOf(detail, '注文管理番号')).toHaveText(String(id));
    await expect(valueOf(detail, '注文者')).toContainText(member.lastName);
    await expect(detail.getByText(order.item)).toBeVisible();
    await expect(valueOf(detail, '送料')).toHaveText(order.shipping);
    await expect(valueOf(detail, '代引手数料')).toHaveText(order.codFee!);
    await expect(valueOf(detail, '合計金額')).toHaveText(order.total);
    await expect(valueOf(detail, '配送先氏名')).toHaveText(order.deliveryName);
    await expect(valueOf(detail, '配送先住所')).toHaveText(order.deliveryAddress);
    await expect(valueOf(detail, '配送先電話番号')).toHaveText(order.deliveryTell);
    await expect(valueOf(detail, '支払い方法')).toHaveText('代金引換');
    // 未発送のあいだは取り消せる
    await expect(detail.getByRole('button', { name: '取り消す' })).toBeVisible();

    // 閉じて、発送済みにチェック（押した瞬間に保存される）。再読み込みしても残る
    await detail.getByRole('button', { name: '閉じる' }).click();
    await expect(detail).toBeHidden();
    const shipped = page.getByRole('checkbox', { name: `注文${id}の発送済み` });
    await expect(shipped).not.toBeChecked();
    const saved = page.waitForResponse((res) => res.url().endsWith('/admin/purchase/update') && res.request().method() === 'POST');
    await shipped.check();
    expect((await saved).ok(), '発送済みの保存に失敗').toBe(true);
    await page.reload();
    await waitForAdminReady(page);
    await expect(page.getByRole('checkbox', { name: `注文${id}の発送済み` })).toBeChecked();

    // 発送済みの注文は、詳細に「取り消す」が出ない
    await page.getByRole('button', { name: `注文${id}の詳細を見る` }).click();
    await expect(detail).toBeVisible();
    await expect(valueOf(detail, '注文管理番号')).toHaveText(String(id));
    await expect(detail.getByRole('button', { name: '取り消す' })).toHaveCount(0);
    await detail.getByRole('button', { name: '閉じる' }).click();

    // エクスポート（ファイル名まで）
    await expectExport(page, '注文');
});

test('AE-5 銀行振込の注文を取り消すと、会員のマイページにも取消済と出る', async ({ page, publicPage, member }) => {
    await buyByBankTransfer(publicPage);

    await openAdminTop(page);
    await goToAdminPage(page, '注文一覧', /\/admin\/purchase$/);
    await expectAdminPage(page, '注文一覧');
    await searchList(page, '注文の検索', '注文者名', searchName(member), /\/admin\/purchase\/search/);
    const id = await singleRecordId(page, '注文');

    await page.getByRole('button', { name: `注文${id}の詳細を見る` }).click();
    const detail = page.getByRole('dialog', { name: '注文の詳細' });
    await expect(valueOf(detail, '支払い方法')).toHaveText('銀行振込');

    // 取り消すと、確認のモーダルに銀行振込の案内（返金）が出る。確認で「取り消す」
    await detail.getByRole('button', { name: '取り消す' }).click();
    const confirm = page.getByRole('alertdialog');
    await expect(confirm).toBeVisible();
    await expect(confirm).toContainText('注文を取り消します。よろしいですか？');
    await expect(confirm).toContainText('入金済みの場合、返金はStripeの管理画面で行ってください。');
    await confirm.getByRole('button', { name: '取り消す' }).click();

    // 一覧に戻り、案内が出て、その注文が「取消済」になる（発送済みのチェックは出ない）
    await expect(page).toHaveURL(/\/admin\/purchase\/search/);
    await expect(page.getByRole('status').filter({ hasText: '注文を取り消しました。' })).toBeVisible();
    await expect(page.locator('span:text-is("取消済")')).toBeVisible();
    await expect(page.getByRole('checkbox', { name: `注文${id}の発送済み` })).toHaveCount(0);

    // 会員のマイページの購入履歴：詳細に「取消済」が出て、振込先は出ない
    await publicPage.goto('/mypage');
    await publicPage.getByRole('button', { name: /^注文日.*の詳細を見る$/ }).click();
    const history = publicPage.getByRole('dialog', { name: '購入の詳細' });
    await expect(history).toBeVisible();
    await expect(valueOf(history, '状況')).toHaveText('取消済');
    await expect(valueOf(history, 'お支払い方法')).toHaveText('銀行振込');
    await expect(history.getByText('お振込先')).toHaveCount(0);
});
