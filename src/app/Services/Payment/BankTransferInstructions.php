<?php

namespace App\Services\Payment;

/**
 * 銀行振込の振込先（Stripe の display_bank_transfer_instructions の zengin）と振込金額
 */
final readonly class BankTransferInstructions
{
    public function __construct(
        public string $bankName,
        public string $branchName,
        /** 口座種別（futsu: 普通、toza: 当座） */
        public string $accountType,
        public string $accountNumber,
        public string $accountHolderName,
        /** 振込金額（円） */
        public int $amount,
    ) {}

    /**
     * 口座種別の日本語表記（未知の値はそのまま返す）
     */
    public function accountTypeLabel(): string
    {
        return match ($this->accountType) {
            'futsu' => '普通',
            'toza' => '当座',
            default => $this->accountType,
        };
    }

    /**
     * セッションに保存する配列にする
     *
     * @return array{bank_name: string, branch_name: string, account_type: string, account_number: string, account_holder_name: string, amount: int}
     */
    public function toArray(): array
    {
        return [
            'bank_name' => $this->bankName,
            'branch_name' => $this->branchName,
            'account_type' => $this->accountType,
            'account_number' => $this->accountNumber,
            'account_holder_name' => $this->accountHolderName,
            'amount' => $this->amount,
        ];
    }

    /**
     * 配列から復元する（形式が正しくなければ null）
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        foreach (['bank_name', 'branch_name', 'account_type', 'account_number', 'account_holder_name'] as $key) {
            if (! is_string($data[$key] ?? null)) {
                return null;
            }
        }
        if (! is_int($data['amount'] ?? null)) {
            return null;
        }

        return new self($data['bank_name'], $data['branch_name'], $data['account_type'], $data['account_number'], $data['account_holder_name'], $data['amount']);
    }
}
