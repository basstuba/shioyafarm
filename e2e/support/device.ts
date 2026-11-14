import { fileURLToPath } from 'node:url';
import { test } from '@playwright/test';

/**
 * SP（スマホ）の project で流しているか
 *
 * Playwright の project 名（playwright.config.ts）で判断する。ユーザー画面は 'sp'、管理画面は 'sp-admin'・'sp-admin-auth'・'sp-admin-setup'。
 */
export function isSp(): boolean {
    return /^sp(-|$)/.test(test.info().project.name);
}

/** 管理画面の storageState（ログイン済みの状態）を保存するファイル。PC・SP ごとに1つ（playwright.config.ts と setup が使う） */
export function adminStatePath(device: 'pc' | 'sp'): string {
    return fileURLToPath(new URL(`../.auth/${device}-admin.json`, import.meta.url));
}
