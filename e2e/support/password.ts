import { expect, type Locator } from '@playwright/test';

// パスワード欄の「表示」「非表示」の切り替えボタン

/**
 * 入力欄に対応する切り替えボタンを取る
 *
 * ボタンは aria-controls で入力欄の id を指している。1ページにパスワード欄が複数あっても
 * （会員登録のパスワードと確認用パスワードなど）、どの欄のボタンかをHTMLの構造に頼らず区別できる。
 * ボタンの名前は、いまの状態に合わせて「表示」（伏せ字のとき）か「非表示」（文字が見えるとき）になる。
 */
export async function passwordToggle(field: Locator, name: '表示' | '非表示'): Promise<Locator> {
    const id = await field.getAttribute('id');
    expect(id, 'パスワード欄に id がない').toBeTruthy();
    const page = field.page();
    return page.getByRole('button', { name, exact: true }).and(page.locator(`[aria-controls="${id}"]`));
}
