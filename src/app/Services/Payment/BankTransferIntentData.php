<?php

namespace App\Services\Payment;

/**
 * 銀行振込の PaymentIntent の情報（必要な項目だけ）
 */
final readonly class BankTransferIntentData
{
    public function __construct(
        public string $id,
        /** PaymentIntent の status（振込待ちは requires_action、入金済みは succeeded） */
        public string $status,
        /** PaymentIntent の金額（円） */
        public int $amount,
        /** 振込先（next_action が display_bank_transfer_instructions でないときは null） */
        public ?BankTransferInstructions $instructions,
    ) {}
}
