<?php

namespace App\Services;

use App\Enums\BankAccountType;
use App\Models\Purchase_bank_transfer;
use App\Models\Reservation_bank_transfer;

/**
 * 銀行振込のお振込先の表示用の値（マイページの詳細とサンクスメールで共通）
 *
 * 振込先の行があり、銀行名が null のとき（注文の時点で顧客残高から支払い済み。口座の5項目は null）は、paid を true にする。
 * 口座種別は日本語表記にする。
 */
final readonly class BankTransferInfo
{
    public function __construct(
        public bool $paid,
        public ?string $bankName,
        public ?string $branchName,
        public ?string $accountType,
        public ?string $accountNumber,
        public ?string $accountHolderName,
        public int $amount,
    ) {}

    /**
     * 購入・予約の振込先の行から作る
     */
    public static function fromRow(Purchase_bank_transfer|Reservation_bank_transfer $row): self
    {
        return new self(
            paid: $row->bank_name === null,
            bankName: $row->bank_name,
            branchName: $row->branch_name,
            accountType: $row->account_type === null ? null : BankAccountType::labelFor($row->account_type),
            accountNumber: $row->account_number,
            accountHolderName: $row->account_holder_name,
            amount: $row->amount,
        );
    }
}
