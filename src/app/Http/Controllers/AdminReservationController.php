<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminReservationUpdateRequest;
use App\Http\Requests\ReservationSearchRequest;
use App\Services\AdminReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の予約一覧（表示・検索・発送済みの更新）
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
     */
    public function update(AdminReservationUpdateRequest $request, AdminReservationService $service): JsonResponse
    {
        Log::debug('AdminReservationController@update start');

        $service->updateShipped($request->integer('id'), $request->boolean('shipped'));

        Log::debug('AdminReservationController@update end');

        return response()->json(['id' => $request->integer('id'), 'shipped' => $request->boolean('shipped')]);
    }

    /**
     * 一覧のビュー（検索結果と、検索欄・エクスポートに戻す検索条件）
     *
     * @param  array{shipped: string, date: string, name: string}  $conditions
     */
    private function listView(AdminReservationService $service, array $conditions): View
    {
        return view('admin.reservations', [
            'reservations' => $service->search($conditions)->withQueryString(),
            'conditions' => $conditions,
        ]);
    }
}
