<?php

namespace App\Enums;

/**
 * 管理画面の注文・予約の取消の結果
 */
enum OrderCancelResult
{
    /** 取消済みにできた（銀行振込の未入金は、Stripe の支払いも取り消した） */
    case Canceled;

    /** 取消済みにできた。クレジットカードの注文・予約（返金は農園が Stripe の管理画面で行う。Stripe は呼ばない） */
    case CanceledCard;

    /** 取消済みにできた。銀行振込の入金済み・入金処理中・一部入金済みで、Stripe の支払いはそのまま（返金は農園が Stripe の管理画面で行う） */
    case CanceledAfterPayment;

    /** 取消済みにできた。Stripe の支払いはすでに canceled だった（前回、Stripe の取消だけ成功して保存に失敗した再実行）。取り消したあとの PaymentIntent には一部入金の手がかりが残らないため、入金があったかは分からない */
    case CanceledMaybePaid;

    /** 存在しない注文・予約のため、何も変えなかった */
    case NotFound;

    /** 発送済みのため、何も変えなかった */
    case Shipped;

    /** すでに取り消されているため、何も変えなかった */
    case AlreadyCanceled;

    /** Stripe の呼び出しやデータベースのエラー（ロック待ちのタイムアウトなど）で取り消せなかった（取消済みにしていない） */
    case Failed;

    /**
     * 取消済みにしてよい結果かどうか
     */
    public function isCanceled(): bool
    {
        return in_array($this, [self::Canceled, self::CanceledCard, self::CanceledAfterPayment, self::CanceledMaybePaid], true);
    }
}
