import { expect, type Locator, type Page } from '@playwright/test';
import { isSp } from './device';
import { mailIds, waitForMail, type Mail } from './mailpit';
import { clickAndWaitForLoad, clickAndWaitForNewPage } from './navigation';

// 管理画面の操作（ログイン・確認コード・ログアウト・サイドメニュー・一覧の検索など）
// 管理者はシーダーの管理者。二段階認証（メールの確認コード）はE2E用アプリでもオンのまま、コードは Mailpit のメールから読む。

/** シーダーの管理者（database/seeders/AdminsTableSeeder.php） */
export const ADMIN = {
    email: 'admin@admin.com',
    password: 'admin1234',
};

/** 確認コードのメールの件名（部分一致） */
const CODE_MAIL_SUBJECT = 'ログイン確認コード';

/**
 * ログインページの入力欄を埋めて「ログイン」を押し、結果の画面（確認コードの入力 or エラー）が出るまで待つ
 *
 * 回数制限（メールアドレスとIPごとに1分5回。成功も数える）に当たったときは、案内の秒数だけ待って押し直す。
 * 回数制限に達したリクエストは数えられないので、待てば必ず通る。1回の実行で管理者のログインは
 * 「PC・SPそれぞれ AE-1〜AE-3 で5回前後＋最初の1回」になり、PC → SP が続くと1分以内に5回を超えうるため、ここで吸収する
 * （アプリ側の回数制限は変えない）。誤ったパスワードで出るエラーは「結果」として返す（待たない）。
 */
export async function submitAdminLogin(page: Page, email: string, password: string): Promise<void> {
    const throttled = page.getByText(/ログイン試行の規定数に達しました/);
    const result = page
        .getByRole('heading', { name: '確認コードの入力' })
        .or(page.getByText('メールアドレスまたはパスワードが正しくありません。'))
        .or(throttled);

    for (let attempt = 0; attempt < 3; attempt++) {
        await page.getByLabel('メールアドレス').fill(email);
        await page.getByLabel('パスワード', { exact: true }).fill(password);
        // 押す前のページに前回の回数制限・エラーの表示が残っていても取り違えないよう、新しいページに変わるのを待ってから結果を見る
        await clickAndWaitForNewPage(page.getByRole('button', { name: 'ログイン' }));
        await expect(result).toBeVisible();

        if (!(await throttled.isVisible())) {
            return;
        }
        const seconds = Number((await throttled.innerText()).match(/(\d+)秒後/)?.[1] ?? '60');
        await page.waitForTimeout((seconds + 1) * 1000);
    }
    throw new Error('管理者のログインの回数制限が解けない');
}

/** 確認コードのメールを待って、メールと6桁のコードを返す（exclude：ログイン操作の前の mailIds()。前のテストのメールを読まないため） */
export async function readAdminCodeMail(exclude: Set<string>): Promise<{ mail: Mail; code: string }> {
    const mail = await waitForMail(ADMIN.email, CODE_MAIL_SUBJECT, { exclude });
    // コードは本文の中で1行だけの6桁の数字（ほかの数字は URL・年など）
    const code = mail.text.match(/^\s*(\d{6})\s*$/m)?.[1];
    expect(code, '確認コードのメールに6桁のコードがない').toBeTruthy();
    return { mail, code: code! };
}

/** 確認コードのメールを待って、6桁のコードを取り出す（exclude は readAdminCodeMail と同じ） */
export async function readAdminCode(exclude: Set<string>): Promise<string> {
    return (await readAdminCodeMail(exclude)).code;
}

/** 確認コード入力画面でコードを入れて「ログインする」を押す */
export async function submitAdminCode(page: Page, code: string): Promise<void> {
    await expect(page.getByRole('heading', { name: '確認コードの入力' })).toBeVisible();
    await page.getByLabel('確認コード').fill(code);
    await page.getByRole('button', { name: 'ログインする' }).click();
}

/**
 * 管理者としてログインする（ログインページ → 確認コード → 管理画面トップ）
 *
 * ログインページを開いた状態でも、まだ開いていなくても呼べる（開いていなければ開く）。
 */
export async function loginAsAdmin(page: Page, password: string = ADMIN.password): Promise<void> {
    if (!/\/admin\/login$/.test(page.url())) {
        await page.goto('/admin/login');
    }
    const known = await mailIds(ADMIN.email);
    await submitAdminLogin(page, ADMIN.email, password);
    await submitAdminCode(page, await readAdminCode(known));
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'しおや農園直販サイト管理画面' })).toBeVisible();
}

/** ヘッダーの「ログアウト」を押し、ログインページへ戻るまで待つ（PC・SPともヘッダーに出ている） */
export async function logoutAdmin(page: Page): Promise<void> {
    await clickAndWaitForLoad(page.getByRole('banner').getByRole('button', { name: 'ログアウト' }), /\/admin\/login$/);
}

/**
 * Alpine（サイドメニュー・一覧のチェックなど）が起動するまで待つ
 *
 * サイドメニューは起動するまで x-cloak で隠れているので、メニューが見えるようになる（アクセシビリティツリーに出る）のを目印にする。
 * 起動前に「メニュー」ボタンや発送済みのチェックを押すと、取りこぼされる。サイドメニューのないページ（トップ・ログイン）では使わない。
 */
export async function waitForAdminReady(page: Page): Promise<void> {
    await page.waitForLoadState('load');
    await expect(page.getByRole('complementary', { name: '管理画面のメニュー' })).toHaveCount(1);
}

/** 本文のページタイトル（h1）が出るまで待ち、Alpine の起動も待つ（サイドメニューのあるページ） */
export async function expectAdminPage(page: Page, title: string): Promise<void> {
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await waitForAdminReady(page);
}

/**
 * 管理画面のリンクを名前で押して、そのページへ移る
 *
 * 管理画面トップでは、本文のカードのリンク。それ以外のページでは、サイドメニューのリンク
 * （SPではヘッダーの「メニュー」ボタンで開く。PCで畳んであれば「メニューを開く」で開く）。
 */
export async function goToAdminPage(page: Page, label: string, url: RegExp): Promise<void> {
    const onTop = /\/admin\/?$/.test(new URL(page.url()).pathname);
    if (onTop) {
        await clickAndWaitForLoad(page.getByRole('main').getByRole('link', { name: label, exact: true }), url);
        return;
    }

    await waitForAdminReady(page);
    const link: Locator = page.getByRole('navigation', { name: '管理画面のページ' }).getByRole('link', { name: label, exact: true });
    if (!(await link.isVisible())) {
        // SP：ヘッダーの「メニュー」で開く。PC：畳んだ帯の「メニューを開く」で開く
        const opener = isSp() ? page.getByRole('banner').getByRole('button', { name: 'メニュー', exact: true }) : page.getByRole('button', { name: 'メニューを開く' });
        await opener.click();
    }
    await clickAndWaitForLoad(link, url);
}

/** 管理画面トップを開く（サイドメニューの「トップページ」ではなく URL で開く。流れの起点に使う） */
export async function openAdminTop(page: Page): Promise<void> {
    await page.goto('/admin');
    await expect(page.getByRole('heading', { level: 1, name: 'しおや農園直販サイト管理画面' })).toBeVisible();
}

/**
 * 一覧の検索欄に入力して「検索」を押し、検索結果のページが開くまで待つ
 *
 * form：検索フォームの名前（「注文の検索」「予約の検索」「会員の検索」）、field：入力欄のラベル（「注文者名」「予約者名」「氏名」）。
 */
export async function searchList(page: Page, form: string, field: string, value: string, url: RegExp): Promise<void> {
    const search = page.getByRole('search', { name: form });
    await search.getByLabel(field).fill(value);
    await clickAndWaitForLoad(search.getByRole('button', { name: '検索', exact: true }), url);
    await waitForAdminReady(page);
}

/** 注文・予約の一覧で、詳細ボタンの名前（「注文12の詳細を見る」）から管理番号を読む。検索結果が1件のときに使う */
export async function singleRecordId(page: Page, subject: '注文' | '予約'): Promise<number> {
    const button = page.getByRole('button', { name: new RegExp(`^${subject}\\d+の詳細を見る$`) });
    await expect(button).toHaveCount(1);
    const label = (await button.getAttribute('aria-label')) ?? '';
    return Number(label.match(/\d+/)![0]);
}

/** 日本時間の今日の日付を YYYYMMDD で返す（エクスポートのファイル名。アプリのタイムゾーンは Asia/Tokyo） */
export function jstDate(date: Date = new Date()): string {
    return new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit' })
        .format(date)
        .replaceAll('-', '');
}

/**
 * 「エクスポート」を押して、ダウンロードされたファイル名を確かめる
 *
 * ファイル名は「{注文|予約}一覧_YYYYMMDD.xlsx」（日本時間）。日付の変わり目をまたいでもよいよう、押す前と後の日付のどちらかと一致すればよい。
 * 列や中身は PHPUnit と UAT で確認済みなので、ここでは見ない。
 */
export async function expectExport(page: Page, subject: '注文' | '予約'): Promise<void> {
    const before = jstDate();
    const [download] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'エクスポート' }).click()]);
    const after = jstDate();
    expect([`${subject}一覧_${before}.xlsx`, `${subject}一覧_${after}.xlsx`]).toContain(download.suggestedFilename());
}
