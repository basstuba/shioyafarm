import { expect, request } from '@playwright/test';

// Mailpit の API（http://mail:8025/api/v1）で、テストが送らせたメールを取り出す

const MAILPIT_URL = process.env.MAILPIT_URL ?? 'http://mail:8025';

/** Mailpit から取り出したメール */
export type Mail = {
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
 * 宛先と件名（部分一致）でメールを待って取り出す
 *
 * メールは同期送信なので画面の遷移と同時に届いているはずだが、念のため数秒待つ。
 * 宛先はテストごとに作る会員のメールアドレスなので、ほかのテストのメールと混ざらない。
 */
export async function waitForMail(to: string, subject: string): Promise<Mail> {
    const api = await request.newContext({ baseURL: MAILPIT_URL });
    try {
        let id: string | undefined;
        await expect
            .poll(
                async () => {
                    const res = await api.get('/api/v1/search', { params: { query: `to:"${to}"` } });
                    // Mailpit のエラーを「メールが届かない」と取り違えないよう、ステータスを先に確かめる
                    expect(res.ok(), `Mailpit の検索に失敗（${res.status()}）`).toBe(true);
                    const body = (await res.json()) as SearchResponse;
                    id = body.messages.find((m) => m.Subject.includes(subject))?.ID;
                    return id;
                },
                { message: `${to} 宛ての「${subject}」のメールが届かない`, timeout: 10_000 },
            )
            .toBeTruthy();

        const res = await api.get(`/api/v1/message/${id}`);
        expect(res.ok(), `Mailpit からメールを取得できない（${res.status()}）`).toBe(true);
        const message = (await res.json()) as MessageResponse;
        return {
            subject: message.Subject,
            to: message.To.map((t) => t.Address),
            text: message.Text,
        };
    } finally {
        await api.dispose();
    }
}
