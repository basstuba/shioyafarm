<?php

namespace App\Repositories\Concerns;

use App\Enums\OrderCancelResult;
use App\Models\Purchase_history;
use App\Models\Reservation;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 管理画面の注文・予約の取消（行をロックして判定し、取消日時を入れる）
 *
 * 対象のモデルは、shipped・canceled_at のカラムを持つこと。
 */
trait CancelsOrder
{
    /**
     * 行をロックして、存在・発送済み・取消済みを判定し、取り消せるときだけ $beforeCancel を呼んでから取消日時を入れる
     *
     * 行のロックは、$beforeCancel（Stripe の支払いの取消など）の間も、取消日時を入れるまで持つ。
     * 発送済みの更新（updateShipped）は、取消済みの行を更新しない条件つきの1回の更新のため、取消との競合で状態が矛盾しない。
     * $beforeCancel には、支払方法（payment_method の値）と、銀行振込の PaymentIntent の ID（振込先の行があるときだけ。ないときは null）を渡す
     * （呼び出し側の Service が、Eloquent のモデルを知らずに済むように）。
     * $beforeCancel が取消済みにしてよい結果（OrderCancelResult::isCanceled）以外を返したときは、取消日時を入れずにその結果を返す。
     *
     * @template TModel of Purchase_history|Reservation
     *
     * @param  Builder<TModel>  $query  対象のテーブルのクエリ（$beforeCancel に必要な関連は with で読み込んでおく）
     * @param  Closure(string, string|null): OrderCancelResult  $beforeCancel  取り消せるときに、取消日時を入れる前に呼ぶ処理
     * @param  'purchaseBankTransfer'|'reservationBankTransfer'  $transferRelation  振込先のリレーション名
     */
    private function cancelLocked(Builder $query, int $id, Closure $beforeCancel, string $transferRelation): OrderCancelResult
    {
        return DB::transaction(function () use ($query, $id, $beforeCancel, $transferRelation): OrderCancelResult {
            $order = $query->whereKey($id)->lockForUpdate()->first();

            if ($order === null) {
                return OrderCancelResult::NotFound;
            }
            if ($order->shipped) {
                return OrderCancelResult::Shipped;
            }
            if ($order->canceled_at !== null) {
                return OrderCancelResult::AlreadyCanceled;
            }

            $result = $beforeCancel($order->payment_method, $order->{$transferRelation}?->stripe_payment_intent_id);
            if ($result->isCanceled()) {
                $order->forceFill(['canceled_at' => now()])->save();
            }

            return $result;
        });
    }
}
