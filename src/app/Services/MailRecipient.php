<?php

namespace App\Services;

use App\Support\MailTarget;
use Carbon\CarbonInterface;

/**
 * 管理画面のメール作成の宛先（宛先の種類とID（target）と、メール作成ページに表示する氏名・メールアドレスと、返信のときに引用する元のお問い合わせ）
 */
final readonly class MailRecipient
{
    /**
     * @param  CarbonInterface|null  $inquiredAt  元のお問い合わせの問い合わせ日時（お問い合わせへの返信のときだけ。アプリのタイムゾーン＝日本時間のまま、一覧の問い合わせ日と同じ形で表示する）
     * @param  string|null  $inquiryDetail  元のお問い合わせの内容（お問い合わせへの返信のときだけ。会員宛てには引用を付けない）
     */
    public function __construct(
        public MailTarget $target,
        public string $fullName,
        public string $email,
        public ?CarbonInterface $inquiredAt = null,
        public ?string $inquiryDetail = null,
    ) {}
}
