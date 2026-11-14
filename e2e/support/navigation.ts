import { type Locator, type Page } from '@playwright/test';

// ページ遷移の待ち合わせ

/**
 * 指定のURLのページが読み込み完了するまで待つ
 *
 * リンクの click() は遷移の開始までしか待たず、load は待たない。
 * 直後に Livewire・Alpine の要素（数量の変更・ボタンなど）を操作すると、起動前で取りこぼされたり、
 * 遷移前のページの同名の要素に操作が当たったりする。遷移先の操作の前に、これで読み込み完了を待つ。
 */
export async function waitForLoad(page: Page, url: string | RegExp): Promise<void> {
    await page.waitForURL(url);
}

/** リンク（ボタン）を押し、指定のURLのページが読み込み完了するまで待つ */
export async function clickAndWaitForLoad(target: Locator, url: string | RegExp): Promise<void> {
    await target.click();
    await waitForLoad(target.page(), url);
}

/**
 * 目印をつけた現在のページが、新しいページ（読み込み直し）に置き換わるまで待つ
 *
 * 同じURLへ戻るフォーム送信（エラー表示のログインなど）では URL で待てず、遷移の前のページに残った表示（前回のエラーなど）を
 * 結果と取り違えうる。押す前に window へ目印を付け、押したあとにそれが消える（＝新しいページが読み込まれた）のを待つ。
 * 押したあとに必ずページが読み込み直される操作（フォームの送信）で使う。
 */
export async function clickAndWaitForNewPage(target: Locator): Promise<void> {
    const page = target.page();
    await page.evaluate(() => {
        (window as unknown as { __e2eOldPage?: boolean }).__e2eOldPage = true;
    });
    await target.click();
    await page.waitForFunction(() => !(window as unknown as { __e2eOldPage?: boolean }).__e2eOldPage);
    await page.waitForLoadState('load');
}
