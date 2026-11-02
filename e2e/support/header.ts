import { expect, test, type Locator, type Page } from '@playwright/test';

// ヘッダーのナビの操作
// SP（768px未満）では、ヘッダーに出る項目（ログイン・マイページ・カート・オンラインショップのいずれか）と、
// 「メニュー」ボタンで開くパネルに入る項目（お知らせ・ログアウトなど）に分かれる。
// どちらにあるかはページごとに変わるので、テストからは項目名だけで操作できるようにする。

type Role = 'link' | 'button';

/** PCではすべての項目がヘッダーに並ぶ。SPかどうかは Playwright の project 名（playwright.config.ts の 'sp'）で判断する */
function isSp(): boolean {
    return test.info().project.name === 'sp';
}

function header(page: Page): Locator {
    return page.getByRole('banner');
}

function menuButton(page: Page): Locator {
    return header(page).getByRole('button', { name: 'メニュー', exact: true });
}

function menuPanel(page: Page): Locator {
    return page.getByRole('navigation', { name: 'メニュー', exact: true });
}

/**
 * SPのヘッダーが使える状態になるまで待つ
 *
 * メニューボタンは Alpine が起動するまで反応しない。また、項目の出し分けもページの読み込みが終わってからでないと確定しない。
 * そのため読み込み完了を待ってから、ボタンが出ていることを確かめる。
 */
async function waitForHeaderReady(page: Page): Promise<void> {
    await page.waitForLoadState('load');
    // ヘッダーは全ページで同じ描画なので、メニューボタンが出ていれば項目の出し分けも済んでいる
    await expect(header(page).getByRole('button', { name: /^(メニュー|閉じる)$/ })).toBeVisible();
}

/** SPのメニューを開く（すでに開いていれば何もしない） */
async function openMenu(page: Page): Promise<void> {
    await waitForHeaderReady(page);
    const button = menuButton(page);
    if (await button.isVisible()) {
        await button.click();
    }
    await expect(menuPanel(page)).toBeVisible();
}

/**
 * ヘッダーの項目を押す
 *
 * SPでヘッダーに出ている項目はそのまま押し、出ていない項目は「メニュー」を開いて押す。
 */
export async function clickHeaderItem(page: Page, name: string, role: Role = 'link'): Promise<void> {
    const item = header(page).getByRole(role, { name, exact: true });
    if (!isSp()) {
        await item.click();
        return;
    }
    await waitForHeaderReady(page);
    if (await item.isVisible()) {
        await item.click();
        return;
    }
    await openMenu(page);
    await menuPanel(page).getByRole(role, { name, exact: true }).click();
}

/** SPのメニューを閉じる（開いていれば） */
async function closeMenu(page: Page): Promise<void> {
    const button = header(page).getByRole('button', { name: '閉じる', exact: true });
    if (await button.isVisible()) {
        await button.click();
    }
    await expect(menuPanel(page)).toBeHidden();
}

/**
 * ヘッダー（SPではヘッダーかメニューの中）に項目がある・ないことを確かめる
 *
 * SPでヘッダーに出ていない項目は、実際にメニューを開いて確かめる（閉じている間は項目が非表示で、名前も取れないため）。
 */
export async function expectHeaderItem(page: Page, name: string, role: Role, exists: boolean): Promise<void> {
    const onHeader = header(page).getByRole(role, { name, exact: true });
    if (!isSp()) {
        await (exists ? expect(onHeader).toBeVisible() : expect(onHeader).toHaveCount(0));
        return;
    }
    await waitForHeaderReady(page);
    if (await onHeader.isVisible()) {
        expect(exists, `${name} がヘッダーにある`).toBe(true);
        return;
    }
    await openMenu(page);
    const inMenu = menuPanel(page).getByRole(role, { name, exact: true });
    await (exists ? expect(inMenu).toBeVisible() : expect(inMenu).toHaveCount(0));
    await closeMenu(page);
}
