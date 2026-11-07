<?php

namespace App\Http\Controllers;

use App\Enums\AdminListType;
use App\Enums\ShippedUpdateResult;
use App\Http\Requests\AdminReservationCancelRequest;
use App\Http\Requests\AdminReservationUpdateRequest;
use App\Http\Requests\ReservationSearchRequest;
use App\Http\Responses\OrderCancelResponder;
use App\Services\AdminOrderCancelService;
use App\Services\AdminReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の予約一覧（表示・検索・発送済みの更新・取消）
 *
 * 詳細のモーダルは Livewire の AdminReservationDetail が表示し、エクスポートは AdminExportController が受ける。
 */
class AdminReservationController extends Controller
{
    /**
     * 予約一覧（検索条件なしの全件）
     */
    public function index(AdminReservationService $service): View
    {
        Log::debug('AdminReservationController@index start');

        $view = $this->listView($service, ['shipped' => '', 'date' => '', 'name' => '']);

        Log::debug('AdminReservationController@index end');

        return $view;
    }

    /**
     * 予約一覧の検索（発送状況・予約日・予約者名。ページ送りでも検索条件を保つ）
     */
    public function search(ReservationSearchRequest $request, AdminReservationService $service): View
    {
        Log::debug('AdminReservationController@search start');

        $view = $this->listView($service, $request->conditions());

        Log::debug('AdminReservationController@search end');

        return $view;
    }

    /**
     * 発送済みかどうかの更新（チェックを押した瞬間に Alpine から送られる。JSON を返す）
     *
     * 取消済みの予約は更新せず 409、存在しない予約（検証のあとに物理削除されたときなど）は 404 を返す（一覧のチェックと行の色を戻す）。
     */
    public function update(AdminReservationUpdateRequest $request, AdminReservationService $service): JsonResponse
    {
        Log::debug('AdminReservationController@update start');

        $result = $service->updateShipped($request->integer('id'), $request->boolean('shipped'));
        $response = match ($result) {
            ShippedUpdateResult::Updated => response()->json(['id' => $request->integer('id'), 'shipped' => $request->boolean('shipped')]),
            ShippedUpdateResult::NotFound => response()->json(['message' => $result->message(AdminListType::Reservation)], 404),
            ShippedUpdateResult::Canceled => response()->json(['message' => $result->message(AdminListType::Reservation)], 409),
        };

        Log::debug('AdminReservationController@update end');

        return $response;
    }

    /**
     * 予約の取消（成功しても失敗しても、取消の前に見ていた検索条件・ページの一覧に戻り、案内かエラーを検索欄の下に表示する）
     *
     * 存在・発送済み・取消済みの判定と、銀行振込の Stripe の支払いの取消は、ロックを取る AdminOrderCancelService だけで行う。エラーの文言は OrderCancelResponder にまとめる。
     */
    public function cancel(AdminReservationCancelRequest $request, AdminOrderCancelService $service, OrderCancelResponder $responder): RedirectResponse
    {
        Log::debug('AdminReservationController@cancel start');

        $response = $responder(AdminListType::Reservation, $service->cancel(AdminListType::Reservation, $request->integer('id')));

        Log::debug('AdminReservationController@cancel end');

        return $response;
    }

    /**
     * 一覧のビュー（検索結果と、検索欄・エクスポートに戻す検索条件）
     *
     * @param  array{shipped: string, date: string, name: string}  $conditions
     */
    private function listView(AdminReservationService $service, array $conditions): View
    {
        $records = $service->search($conditions)->withQueryString();

        return view('admin.reservations', [
            'reservations' => $records,
            'conditions' => $conditions,
            'exportCount' => $service->exportCount($conditions, $records->total()),
        ]);
    }
}
