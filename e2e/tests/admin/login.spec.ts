import { test, expect, type Page } from '@playwright/test';
import { ADMIN, loginAsAdmin, logoutAdmin, readAdminCode, readAdminCodeMail, submitAdminCode, submitAdminLogin } from '../../support/admin';
import { mailIds, waitForMail, type Mail } from '../../support/mailpit';
import { clickAndWaitForLoad, clickAndWaitForNewPage, waitForLoad } from '../../support/navigation';

// 管理者のログイン・ログアウト・パスワード再設定の流れ（docs/e2e/admin.md AE-1〜AE-3）
// この3項目だけは、ログイン済みの状態（storageState）を使わず、ログインから始める（playwright.config.ts の *-admin-auth）。
// AE-3 は管理者のパスワードを変えるので、ログイン済みの状態を保存する *-admin-setup より前に流す（設定の説明を見る）。

test('AE-1 管理者がログイン（二段階認証）して管理画面に入り、ログアウトする', async ({ page }) => {
    // 未ログインで管理画面を開くと、管理者用のログインページへ移る
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.getByRole('heading', { name: '管理者用ログイン' })).toBeVisible();

    // パスワードを通ると、確認コードの入力画面へ進み、コードのメールが届く
    const known = await mailIds(ADMIN.email);
    await submitAdminLogin(page, ADMIN.email, ADMIN.password);
    await expect(page).toHaveURL(/\/admin\/two-factor$/);
    const { mail, code } = await readAdminCodeMail(known);
    expect(mail.subject).toContain('ログイン確認コード');
    expect(mail.text).toMatch(/^\s*\d{6}\s*$/m);
    await submitAdminCode(page, code);

    // 管理画面トップに「各種一覧」「作成・登録」のリンクが出る
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { level: 1, name: 'しおや農園直販サイト管理画面' })).toBeVisible();
    const lists = page.getByRole('navigation', { name: '各種一覧' });
    await expect(page.getByRole('heading', { name: '各種一覧' })).toBeVisible();
    await expect(lists.getByRole('link', { name: '注文一覧' })).toBeVisible();
    await expect(lists.getByRole('link', { name: '資材一覧' })).toBeVisible();
    const creates = page.getByRole('navigation', { name: '作成・登録' });
    await expect(page.getByRole('heading', { name: '作成・登録' })).toBeVisible();
    await expect(creates.getByRole('link', { name: 'お知らせ作成' })).toBeVisible();
    await expect(creates.getByRole('link', { name: '掲載商品登録' })).toBeVisible();

    // ログアウトでログインページへ戻り、そのあと管理画面を開いてもログインページへ移る
    await logoutAdmin(page);
    await expect(page.getByRole('heading', { name: '管理者用ログイン' })).toBeVisible();
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);
});

test('AE-2 誤ったパスワード・誤った確認コードでは管理画面に入れない', async ({ page }) => {
    await page.goto('/admin/login');

    // 誤ったパスワードでは、ログインページに留まり、エラーが出る
    await submitAdminLogin(page, ADMIN.email, 'wrong-password');
    await expect(page).toHaveURL(/\/admin\/login$/);
    await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible();

    // 正しいパスワードでは確認コードの入力画面へ進むが、誤ったコードでは管理画面に入れない
    const known = await mailIds(ADMIN.email);
    await submitAdminLogin(page, ADMIN.email, ADMIN.password);
    await expect(page).toHaveURL(/\/admin\/two-factor$/);
    const code = await readAdminCode(known);
    await submitAdminCode(page, code === '000000' ? '111111' : '000000');
    await expect(page).toHaveURL(/\/admin\/two-factor$/);
    await expect(page.getByText('確認コードが正しくありません。')).toBeVisible();

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login$/);
});

test('AE-3 管理者のパスワードを再設定し、新しいパスワードでログインする（最後に元のパスワードへ戻す）', async ({ page, browser }) => {
    const newPassword = 'e2eAdminNew456';
    // 再設定を実際に送ったか（途中で失敗しても元に戻すための印。送る直前に立てる）
    let passwordMayHaveChanged = false;

    try {
        await requestResetAndSetPassword(page, newPassword, () => {
            passwordMayHaveChanged = true;
        });
        await expect(page).toHaveURL(/\/admin\/login$/);
        await expect(page.getByRole('status')).toHaveText('パスワードを再設定しました。新しいパスワードでログインしてください。');

        // 新しいパスワードではログインできる（確認コードも入れる）
        await loginAsAdmin(page, newPassword);
        await logoutAdmin(page);

        // 古いパスワードではログインできない
        await submitAdminLogin(page, ADMIN.email, ADMIN.password);
        await expect(page).toHaveURL(/\/admin\/login$/);
        await expect(page.getByText('メールアドレスまたはパスワードが正しくありません。')).toBeVisible();
    } finally {
        if (passwordMayHaveChanged) {
            // 失敗しても管理者のパスワードを元（シーダーの admin1234）に戻す。ページの状態に左右されないよう、新しいコンテキストで行う
            const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
            try {
                await requestResetAndSetPassword(await context.newPage(), ADMIN.password);
            } finally {
                await context.close();
            }
        }
    }

    // 元のパスワードに戻ったことは、このあとの管理画面のログイン（*-admin-setup。シーダーのパスワードでログインする）で確かめられる。
    // ここでもう一度ログインすると、1分5回の回数制限（AE-1〜AE-3 で5回使う）に当たって、1分近く待つことになる
});

/**
 * ログインページの「パスワードをお忘れの方」から再設定メールを送り、メールのリンクで新しいパスワードを設定する（送信まで。ログイン画面へ移るのは呼び出し側が確かめる）
 *
 * beforeSubmit は、パスワードを設定する送信の直前に呼ぶ（変わったかもしれない印を立てるため）。
 * 再設定メールは、同じ管理者へ1分以内に続けて送れない（アプリの仕様）。ただし、再設定が終わるとトークンは消えるので、続けて元に戻す再設定は送れる。
 */
async function requestResetAndSetPassword(page: Page, password: string, beforeSubmit: () => void = () => {}): Promise<void> {
    const mail = await requestResetMail(page);
    const resetUrl = mail.text.match(/https?:\/\/[^\s)<>"']+\/admin\/reset-password\/[^\s)<>"']+/)?.[0];
    expect(resetUrl, '再設定メールにリンクがない').toBeTruthy();

    await page.goto(resetUrl!);
    await expect(page.getByRole('heading', { name: '新しいパスワードの設定' })).toBeVisible();
    await page.getByLabel('新しいパスワード').fill(password);
    await page.getByLabel('確認用パスワード').fill(password);
    beforeSubmit();
    await page.getByRole('button', { name: 'パスワードを再設定する' }).click();
    await waitForLoad(page, /\/admin\/login$/);
}

/**
 * 再設定メールの送信を申請し、届いたメールを返す
 *
 * 次の2つの待ちは、待てば通るので、待って送り直す（最大3回）。
 * - 同じメールアドレスへの再送は60秒あける（パスワードブローカーの throttle）。管理者のメールアドレスかどうかを漏らさないため、
 *   画面には送れたときと同じ案内（status）が出るだけでメールは送られない。案内のあとメールが届かなければ、これに当たったとみなして61秒待つ。
 * - 送信の回数制限（IPごと・ルートごとに1分5回）。画面に「操作の回数が上限に達しました。N秒後に〜」と出るので、その秒数（なければ60秒）だけ待つ。
 */
async function requestResetMail(page: Page): Promise<Mail> {
    const sent = page.getByRole('status').filter({ hasText: 'ご登録のメールアドレス宛てに、パスワード再設定用のメールをお送りしました。' });
    const rateLimited = page.getByText(/操作の回数が上限に達しました/).first();

    for (let attempt = 0; attempt < 3; attempt++) {
        // 毎回ページを開き直して、前回の案内が残った状態で結果を見ないようにする
        await page.goto('/admin/login');
        await clickAndWaitForLoad(page.getByRole('link', { name: 'パスワードをお忘れの方' }), /\/admin\/forgot-password$/);

        const known = await mailIds(ADMIN.email);
        await page.getByLabel('メールアドレス').fill(ADMIN.email);
        await clickAndWaitForNewPage(page.getByRole('button', { name: '再設定メールを送信' }));
        await expect(sent.or(rateLimited)).toBeVisible();

        let waitSeconds: number;
        if (await rateLimited.isVisible()) {
            waitSeconds = Number((await rateLimited.innerText()).match(/(\d+)秒後/)?.[1] ?? '60') + 1;
        } else {
            try {
                return await waitForMail(ADMIN.email, 'パスワード再設定のご案内', { exclude: known, timeout: 5_000 });
            } catch {
                waitSeconds = 61;
            }
        }
        await page.waitForTimeout(waitSeconds * 1000);
    }
    throw new Error('パスワード再設定メールの送信の待ちが解けない（再設定メールの再送の待ち・送信の回数制限）');
}
