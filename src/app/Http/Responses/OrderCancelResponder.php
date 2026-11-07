<?php

namespace App\Http\Responses;

use App\Enums\AdminListType;
use App\Enums\OrderCancelResult;
use App\Support\AdminListUrl;
use Illuminate\Http\RedirectResponse;

/**
 * 管理画面の注文・予約の取消の結果を、一覧へ戻る応答にする（注文と予約のコントローラーで共有する）
 *
 * 成功しても失敗しても、取消の前に見ていた検索条件・ページの一覧に戻る。
 * 成功の案内は flash の list_status（検索欄の下に緑の文字）、取り消せなかった理由は errors の cancel（検索欄の下に赤字）に入れる。
 */
class OrderCancelResponder
{
    /** Stripe の呼び出しなどで取り消せなかったときの案内 */
    public const string FAILED_MESSAGE = '取り消せませんでした。時間をおいて、もう一度お試しください。';

    public function __invoke(AdminListType $type, OrderCancelResult $result): RedirectResponse
    {
        $subject = $type->subject();
        $redirect = redirect(AdminListUrl::previousOrIndex($type->listRoute(), $type->searchRoute()));

        return match ($result) {
            OrderCancelResult::Canceled => $redirect->with('list_status', "{$subject}を取り消しました。"),
            OrderCancelResult::CanceledCard => $redirect->with('list_status', "{$subject}を取り消しました。クレジットカードの返金は、Stripeの管理画面で行ってください。"),
            OrderCancelResult::CanceledAfterPayment => $redirect->with('list_status', "{$subject}を取り消しました。入金済みのため、返金はStripeの管理画面で行ってください。"),
            OrderCancelResult::NotFound => $redirect->withErrors(['cancel' => "{$subject}が見つかりません。"]),
            OrderCancelResult::Shipped => $redirect->withErrors(['cancel' => '発送済みのため取り消せません。']),
            OrderCancelResult::AlreadyCanceled => $redirect->withErrors(['cancel' => 'すでに取り消されています。']),
            OrderCancelResult::Failed => $redirect->withErrors(['cancel' => self::FAILED_MESSAGE]),
        };
    }
}
