import { test as base } from '@playwright/test';
import { newMember, register, type Member } from './member';

// 会員登録してログインした状態から始めるテスト用の fixture

type Fixtures = {
    /** 会員登録済み・ログイン済みの会員（北海道在住） */
    member: Member;
};

export const test = base.extend<Fixtures>({
    member: async ({ page }, use) => {
        const member = newMember();
        await register(page, member);
        await use(member);
    },
});

export { expect } from '@playwright/test';
