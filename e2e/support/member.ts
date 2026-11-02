import { expect, type Page } from '@playwright/test';
import { clickHeaderItem } from './header';
import { passwordToggle } from './password';

// テストごとに新しく作る会員（Stripe の顧客残高などの影響を受けないよう、毎回別のメールアドレスにする）

/** 会員登録で入力する値 */
export type Member = {
    lastName: string;
    firstName: string;
    prefecture: string;
    city: string;
    tell: string;
    email: string;
    password: string;
};

let sequence = 0;

/** 新しい会員の入力値を作る（まだ登録はしない） */
export function newMember(overrides: Partial<Member> = {}): Member {
    sequence += 1;
    return {
        lastName: '試験',
        firstName: '太郎',
        prefecture: '北海道',
        city: '札幌市中央区E2E1-1',
        tell: '09012345678',
        // メールアドレスは30文字以内（会員登録の入力ルール）。時刻は36進数にして短くする
        email: `e2e-${Date.now().toString(36)}-${sequence}@example.com`,
        password: 'e2epass123',
        ...overrides,
    };
}

/** 会員登録ページの入力欄を埋めて「登録する」を押す（会員登録ページを開いた状態で呼ぶ） */
export async function fillRegisterForm(page: Page, member: Member): Promise<void> {
    await page.getByLabel('姓').fill(member.lastName);
    await page.getByLabel('名', { exact: true }).fill(member.firstName);
    await page.getByLabel('お住まいの地域').selectOption(member.prefecture);
    await page.getByLabel('ご住所').fill(member.city);
    await page.getByLabel('電話番号').fill(member.tell);
    await page.getByLabel('メールアドレス').fill(member.email);
    await page.getByLabel('パスワード', { exact: true }).fill(member.password);
    await page.getByLabel('確認用パスワード').fill(member.password);
    await page.getByRole('button', { name: '登録する' }).click();
}

/** 会員登録ページから登録する（登録するとログイン状態になる） */
export async function register(page: Page, member: Member): Promise<void> {
    await page.goto('/register');
    await fillRegisterForm(page, member);
    await expect(page).not.toHaveURL(/\/register$/);
}

/**
 * ログインページの入力欄を埋めて「ログイン」を押す（ログインページを開いた状態で呼ぶ）
 *
 * showPassword を true にすると、送信前にパスワード欄の「表示」を押して文字が見える状態にする。
 */
export async function fillLoginForm(page: Page, email: string, password: string, options: { showPassword?: boolean } = {}): Promise<void> {
    await page.getByLabel('メールアドレス').fill(email);
    const passwordField = page.getByLabel('パスワード');
    await passwordField.fill(password);
    if (options.showPassword) {
        await (await passwordToggle(passwordField, '表示')).click();
        await expect(passwordField).toHaveAttribute('type', 'text');
    }
    await page.getByRole('button', { name: 'ログイン' }).click();
}

/** ヘッダーの「ログアウト」を押す（SPではメニューを開いてから押す） */
export async function logout(page: Page): Promise<void> {
    await clickHeaderItem(page, 'ログアウト', 'button');
}
