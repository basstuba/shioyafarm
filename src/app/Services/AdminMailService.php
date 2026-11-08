<?php

namespace App\Services;

use App\Enums\MailRecipientType;
use App\Enums\MailSendResult;
use App\Mail\AdminMessageMail;
use App\Repositories\Interfaces\InquiryRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Support\MailTarget;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 管理画面のメール作成・送信サービス（会員宛て・お問い合わせへの返信）
 *
 * 宛先のメールアドレスは画面から受け取らず、宛先の種類とIDからここで引き直す（任意の宛先には送れない）。
 * 送信は同期（キューを使わない）。送ったメールの履歴は保存せず、お問い合わせの対応済みも変えない。
 */
class AdminMailService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InquiryRepositoryInterface $inquiryRepository,
        private readonly AdminMailSendStore $sendStore,
    ) {}

    /**
     * 宛先を取得する（存在しない会員・お問い合わせは null。退会済みの会員も取得する）
     */
    public function findRecipient(MailTarget $target): ?MailRecipient
    {
        if ($target->type === MailRecipientType::User) {
            $user = $this->userRepository->findForAdmin($target->id);

            return $user === null ? null : new MailRecipient($target, $user->full_name, $user->email);
        }

        $inquiry = $this->inquiryRepository->findForAdmin($target->id);

        return $inquiry === null
            ? null
            : new MailRecipient($target, $inquiry->full_name, $inquiry->email, $inquiry->created_at, $inquiry->detail);
    }

    /**
     * メールを送る
     *
     * 同じ宛先に同じ内容を直前に送っているときは送らない（二重送信）。送信に失敗したときは、ログに残して Failed を返す
     * （個人情報を出さないよう、ログには宛先の種類・ID と例外のクラスだけを書く。例外のメッセージには宛先のメールアドレスが入りうるため、メッセージ・本文・タイトルは書かない）。
     */
    public function send(MailTarget $target, string $title, string $body): MailSendResult
    {
        $recipient = $this->findRecipient($target);

        if ($recipient === null) {
            return MailSendResult::NotFound;
        }

        $fingerprint = hash('sha256', json_encode([$target->type->value, $target->id, $title, $body], JSON_THROW_ON_ERROR));

        if (! $this->sendStore->claim($fingerprint)) {
            return MailSendResult::AlreadySent;
        }

        try {
            Mail::send(new AdminMessageMail($recipient, $title, $body));
        } catch (Throwable $e) {
            $this->sendStore->release($fingerprint);

            Log::error('管理画面のメールの送信に失敗しました。', [
                'type' => $target->type->value,
                'id' => $target->id,
                'exception' => $e::class,
            ]);

            return MailSendResult::Failed;
        }

        return MailSendResult::Sent;
    }
}
