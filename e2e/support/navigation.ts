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
