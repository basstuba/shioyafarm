<?php

namespace App\Services;

use App\Enums\AdminListType;
use App\Enums\OrderCancelResult;
use App\Enums\PaymentMethod;
use App\Repositories\Interfaces\PurchaseHistoryRepositoryInterface;
use App\Repositories\Interfaces\ReservationRepositoryInterface;
use App\Services\Payment\PaymentGatewayException;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * 管理画面の注文・予約の取消サービス（注文と予約で共通）
 *
 * 取り消せるのは、未発送で未取消のもの。クレジットカード・代金引換は Stripe を呼ばない（カードの返金は、農園が Stripe の管理画面で行う。カードは CanceledCard で案内を変える）。
 * 銀行振込で振込先の行があるときは、Stripe の PaymentIntent の状態を確認する。
 * - 入金済み（succeeded）・入金処理中（processing）：Stripe は変えずに取消済みにする（返金は農園が Stripe の管理画面で行う。CanceledAfterPayment）
 * - それ以外：PaymentIntent を取り消す。振込の残り（amountRemaining）が PaymentIntent の金額より小さい（一部入金済み。amount_received には反映されない）ときは CanceledAfterPayment、そうでなければ Canceled
 * - すでに取消済み（canceled）：成功扱いにする（前回、Stripe の取消だけ成功して保存に失敗したときの再試行）。取り消したあとは一部入金の手がかりが残らないため、入金があったか分からない CanceledMaybePaid にして、確認の案内を出す
 * - 取り消せない状態・Stripe の例外：取消済みにせず Failed を返す
 * 取消済みにするのは、行をロックしたまま Stripe を呼んだあと（リポジトリの cancel）。
 */
class AdminOrderCancelService
{
    use DetectsConcurrencyErrors;

    public function __construct(
        private readonly PurchaseHistoryRepositoryInterface $purchaseHistoryRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * 注文または予約を取り消す
     *
     * 同時実行のエラー（ロック待ちのタイムアウト・デッドロック。DetectsConcurrencyErrors の判定）は、ログに残して Failed を返す（取消済みにしていない）。
     * それ以外の例外（制約違反・接続エラー・コードの誤りなど）は、隠さずそのまま投げる。
     * 外側のトランザクションの中で呼ばれると DeadlockException になるため、両方を捕まえる。
     *
     * @throws QueryException 同時実行のエラー以外のデータベースのエラー
     */
    public function cancel(AdminListType $type, int $id): OrderCancelResult
    {
        try {
            return match ($type) {
                AdminListType::Purchase => $this->purchaseHistoryRepository->cancel($id, $this->settlePayment(...)),
                AdminListType::Reservation => $this->reservationRepository->cancel($id, $this->settlePayment(...)),
            };
        } catch (QueryException|DeadlockException $e) {
            if (! $this->causedByConcurrencyError($e)) {
                throw $e;
            }

            Log::error('注文・予約の取消に失敗しました。', ['type' => $type->value, 'id' => $id, 'exception' => $e]);

            return OrderCancelResult::Failed;
        }
    }

    /**
     * 取消済みにする前の、Stripe の支払いの処理（行をロックしている間に呼ばれる）
     *
     * @param  string|null  $paymentIntentId  銀行振込の PaymentIntent の ID（振込先の行がないときは null）
     */
    private function settlePayment(string $paymentMethod, ?string $paymentIntentId): OrderCancelResult
    {
        $method = PaymentMethod::tryFrom($paymentMethod);

        // クレジットカード：Stripe は呼ばない（返金は農園が Stripe の管理画面で行う）
        if ($method === PaymentMethod::Card) {
            return OrderCancelResult::CanceledCard;
        }
        // 代金引換、振込先の行がない銀行振込：Stripe は呼ばない
        if (! $method?->usesBankTransfer() || $paymentIntentId === null) {
            return OrderCancelResult::Canceled;
        }

        try {
            $intent = $this->paymentGateway->retrieveBankTransferIntent($paymentIntentId);

            // 入金済み・入金処理中：Stripe は変えない
            if (in_array($intent->status, ['succeeded', 'processing'], true)) {
                return OrderCancelResult::CanceledAfterPayment;
            }
            // すでに取消済み（前回、Stripe の取消だけ成功して保存に失敗したときの再試行）：一部入金があったかは分からないため、確認の案内を出す
            if ($intent->status === 'canceled') {
                return OrderCancelResult::CanceledMaybePaid;
            }

            $this->paymentGateway->cancelPaymentIntent($paymentIntentId);

            // 一部入金済み（振込の残りが PaymentIntent の金額より小さい）：支払いは取り消したうえで、入金済みの案内を出す
            if ($intent->amountRemaining !== null && $intent->amountRemaining < $intent->amount) {
                return OrderCancelResult::CanceledAfterPayment;
            }

            return OrderCancelResult::Canceled;
        } catch (PaymentGatewayException $e) {
            Log::error('銀行振込の PaymentIntent の取消に失敗しました。', ['payment_intent_id' => $paymentIntentId, 'message' => $e->getMessage()]);

            return OrderCancelResult::Failed;
        }
    }
}
