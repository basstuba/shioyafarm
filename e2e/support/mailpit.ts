import { expect, request, type APIRequestContext } from '@playwright/test';

// Mailpit の API（http://mail:8025/api/v1）で、テストが送らせたメールを取り出す

const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://mail:8025';

/** Mailpit から取り出したメール */
export type Mail = {
    id: string;
    subject: string;
    to: string[];
    text: string;
};

type SearchResponse = {
    messages: { ID: string; Subject: string }[];
};

type MessageResponse = {
    Subject: string;
    To: { Address: string }[];
    Text: string;
};

/**
 * 宛先宛てのメールの一覧（ID・件名）を Mailpit から取る
 *
 * Mailpit のエラーを「メールが届かない」と取り違えないよう、ステータスを先に確かめる。
 * request context の作成・破棄もここで行う（api を渡されたときは、呼び出し側のものを使い、破棄しない）。
 */
async function searchMails(to: string, api?: APIRequestContext): Promise<SearchResponse['messages']> {
    const context = api ?? (await request.newContext({ baseURL: MAILPIT_URL }));
    try {
        const res = await context.get('/api/v1/search', { params: { query: `to:"${to}"` } });
        expect(res.ok(), `Mailpit の検索に失敗（${res.status()}）`).toBe(true);
        return ((await res.json()) as SearchResponse).messages;
    } finally {
        if (!api) {
            await context.dispose();
        }
    }
}

/** 宛先のメールの ID の一覧（新しく届いたメールだけを取り出すために、操作の前に控えておく） */
export async function mailIds(to: string): Promise<Set<string>> {
    return new Set((await searchMails(to)).map((m) => m.ID));
}

/**
 * 宛先と件名（部分一致）でメールを待って取り出す
 *
 * メールは同期送信なので画面の遷移と同時に届いているはずだが、念のため数秒待つ（timeout で変えられる）。
 * 宛先はテストごとに作る会員のメールアドレスなので、ほかのテストのメールと混ざらない。
 * 管理者のように宛先が毎回同じで、前のテストのメールが残っているときは、exclude に操作の前の mailIds() を渡して、新しく届いたメールだけを対象にする。
 */
export async function waitForMail(to: string, subject: string, options: { exclude?: Set<string>; timeout?: number } = {}): Promise<Mail> {
    const api = await request.newContext({ baseURL: MAILPIT_URL });
    try {
        let id: string | undefined;
        await expect
            .poll(
                async () => {
                    const messages = await searchMails(to, api);
                    id = messages.find((m) => m.Subject.includes(subject) && !options.exclude?.has(m.ID))?.ID;
                    return id;
                },
                { message: `${to} 宛ての「${subject}」のメールが届かない`, timeout: options.timeout ?? 10_000 },
            )
            .toBeTruthy();

        const res = await api.get(`/api/v1/message/${id}`);
        expect(res.ok(), `Mailpit からメールを取得できない（${res.status()}）`).toBe(true);
        const message = (await res.json()) as MessageResponse;
        return {
            id: id!,
            subject: message.Subject,
            to: message.To.map((t) => t.Address),
            text: message.Text,
        };
    } finally {
        await api.dispose();
    }
}
