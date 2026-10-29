import { test, expect } from '../support/fixtures';
import { waitForMail } from '../support/mailpit';
import { addToCart, changeQuantity, openItem, valueOf } from '../support/shop';

// 購入の流れ（docs/e2e/scenarios.md E-4・E-5）
// 送料は config/shipping.php の値。2kgの商品2つ（4kg）は80サイズで、北海道 1,230円・関東 1,740円

test('E-4 カートから代金引換で購入し、住所を追加して届け先にする', async ({ page, member }) => {
    await addToCart(page, 'じゃがいも 2kg');
    await addToCart(page, '玉ねぎ 2kg');

    await page.goto('/cart');
    await page.getByRole('link', { name: '購入する' }).click();
    await expect(page.getByRole('heading', { name: 'ご注文内容' })).toBeVisible();

    // 初期値は会員登録の氏名・電話番号・住所
    await expect(page.getByLabel('お届け先氏名')).toHaveValue(`${member.lastName}${member.firstName}`);
    await expect(page.getByLabel('お届け先電話番号')).toHaveValue(member.tell);
    await expect(page.getByTestId('confirm-address')).toHaveText(`${member.prefecture}${member.city}`);
    await expect(valueOf(page, '小計')).toHaveText('685円');
    await expect(valueOf(page, '送料')).toHaveText('1,230円');

    // 住所追加モーダルで東京都の住所を追加すると、その住所が選ばれて送料が変わる
    await page.getByRole('button', { name: '＋新しい住所を追加' }).click();
    const modal = page.getByRole('dialog', { name: '新しい住所を追加' });
    await modal.getByLabel('都道府県を選択').selectOption('東京都');
    await modal.getByLabel('住所').fill('千代田区E2E2-2');
    await modal.getByRole('button', { name: '住所を追加' }).click();
    await expect(modal).toBeHidden();
    await expect(page.getByTestId('confirm-address')).toHaveText('東京都千代田区E2E2-2');
    await expect(valueOf(page, '送料')).toHaveText('1,740円');

    // 代金引換を選ぶと代引手数料が加わる
    await expect(valueOf(page, '代引手数料')).toHaveCount(0);
    await page.getByRole('radio', { name: /代金引換/ }).check();
    await expect(valueOf(page, '代引手数料')).toHaveText('330円');
    await expect(valueOf(page, '合計')).toHaveText('2,755円');

    await page.getByRole('button', { name: '注文を確定する' }).click();
    await expect(page.getByRole('heading', { name: /ご注文ありがとうございました/ })).toBeVisible();

    // 購入が終わるとカートは空になる
    await page.goto('/cart');
    await expect(page.getByText('カートに商品はありません')).toBeVisible();

    // サンクスメール
    const mail = await waitForMail(member.email, 'ご注文ありがとうございます');
    expect(mail.text).toContain('お支払方法：代金引換');
    expect(mail.text).toContain('じゃがいも 2kg × 1');
    expect(mail.text).toContain('玉ねぎ 2kg × 1');
    expect(mail.text).toMatch(/代引手数料\s*¥330/);
    expect(mail.text).toMatch(/合計\s*¥2,755/);
    expect(mail.text).toContain('東京都千代田区E2E2-2');

    // マイページの購入履歴の「詳細」
    await page.goto('/mypage');
    await page.getByRole('button', { name: /^注文日.*の詳細を見る$/ }).click();
    const detail = page.getByRole('dialog', { name: '購入の詳細' });
    await expect(detail).toBeVisible();
    await expect(valueOf(detail, '代引手数料')).toHaveText('330円');
    await expect(valueOf(detail, '合計金額')).toHaveText('2,755円');
    await expect(valueOf(detail, 'お支払い方法')).toHaveText('代金引換');
    await expect(detail.getByText('東京都千代田区E2E2-2')).toBeVisible();
});

test('E-5 商品詳細から銀行振込で購入し、振込先のご案内から一度戻る', async ({ page, member }) => {
    // 商品詳細からの購入ではカートを変えないことを確かめるため、先に別の商品をカートに入れておく
    await addToCart(page, '人参 2kg');

    await openItem(page, 'じゃがいも 2kg');
    await page.getByRole('link', { name: '購入する' }).click();
    await changeQuantity(page, 'じゃがいも 2kg', 2);
    await expect(valueOf(page, '小計')).toHaveText('660円');
    await expect(valueOf(page, '送料')).toHaveText('1,230円');
    await page.getByRole('radio', { name: /銀行振込/ }).check();
    await expect(valueOf(page, '合計')).toHaveText('1,890円');
    await page.getByRole('button', { name: '注文を確定する' }).click();

    // 振込先のご案内
    await expect(page.getByRole('heading', { name: '振込先のご案内' })).toBeVisible();
    const instructions = page.getByTestId('bank-transfer-instructions');
    for (const label of ['銀行名', '支店名', '口座種別', '口座番号', '口座名義']) {
        await expect(valueOf(instructions, label)).not.toBeEmpty();
    }
    await expect(valueOf(instructions, '振込金額')).toHaveText('1,890円');
    await expect(page.getByText('じゃがいも 2kg × 2個')).toBeVisible();

    // 「戻る」で購入ページへ。入力内容は残る
    await page.getByRole('button', { name: '戻る' }).click();
    await expect(page.getByRole('heading', { name: 'ご注文内容' })).toBeVisible();
    await expect(page.getByLabel('じゃがいも 2kg の数量')).toHaveValue('2');
    await expect(page.getByRole('radio', { name: /銀行振込/ })).toBeChecked();

    await page.getByRole('button', { name: '注文を確定する' }).click();
    await expect(page.getByRole('heading', { name: '振込先のご案内' })).toBeVisible();
    const bankName = await valueOf(instructions, '銀行名').innerText();
    await page.getByRole('button', { name: '振込み' }).click();
    await expect(page.getByRole('heading', { name: /ご注文ありがとうございました/ })).toBeVisible();

    // 商品詳細からの購入では、カートの中身は変わらない
    await page.goto('/cart');
    await expect(page.getByRole('listitem').filter({ hasText: '人参 2kg' })).toBeVisible();

    const mail = await waitForMail(member.email, 'ご注文ありがとうございます');
    expect(mail.text).toContain('お支払方法：銀行振込');
    expect(mail.text).toContain('じゃがいも 2kg × 2');
    expect(mail.text).toContain(`銀行名：${bankName}`);
    expect(mail.text).toContain('お振込金額：¥1,890');

    await page.goto('/mypage');
    await page.getByRole('button', { name: /^注文日.*の詳細を見る$/ }).click();
    const detail = page.getByRole('dialog', { name: '購入の詳細' });
    await expect(detail.getByText(`銀行名：${bankName}`)).toBeVisible();
    await expect(detail.getByText('お振込金額：1,890円')).toBeVisible();
});
