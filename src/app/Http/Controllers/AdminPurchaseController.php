<?php

namespace App\Http\Controllers;

use App\Enums\AdminListType;
use App\Enums\ShippedUpdateResult;
use App\Http\Requests\AdminPurchaseCancelRequest;
use App\Http\Requests\AdminPurchaseUpdateRequest;
use App\Http\Requests\PurchaseSearchRequest;
use App\Http\Responses\OrderCancelResponder;
use App\Services\AdminOrderCancelService;
use App\Services\AdminPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の注文一覧（表示・検索・発送済みの更新・取消）
 *
 * 詳細のモーダルは Livewire の AdminPurchaseDetail が表示し、エクスポートは AdminExportController が受ける。
 */
class AdminPurchaseController extends Controller
{
    /**
     * 注文一覧（検索条件なしの全件）
     */
    public function index(AdminPurchaseService $service): View
    {
        Log::debug('AdminPurchaseController@index start');

        $view = $this->listView($service, ['shipped' => '', 'date' => '', 'name' => '']);

        Log::debug('AdminPurchaseController@index end');

        return $view;
    }

    /**
     * 注文一覧の検索（発送状況・注文日・注文者名。ページ送りでも検索条件を保つ）
     */
    public function search(PurchaseSearchRequest $request, AdminPurchaseService $service): View
    {
        Log::debug('AdminPurchaseController@search start');

        $view = $this->listView($service, $request->conditions());

        Log::debug('AdminPurchaseController@search end');

        return $view;
    }

    /**
     * 発送済みかどうかの更新（チェックを押した瞬間に Alpine から送られる。JSON を返す）
     *
     * 取消済みの注文は更新せず 409、存在しない注文（検証のあとに物理削除されたときなど）は 404 を返す（一覧のチェックと行の色を戻す）。
     */
    public function update(AdminPurchaseUpdateRequest $request, AdminPurchaseService $service): JsonResponse
    {
        Log::debug('AdminPurchaseController@update start');

        $result = $service->updateShipped($request->integer('id'), $request->boolean('shipped'));
        $response = match ($result) {
            ShippedUpdateResult::Updated => response()->json(['id' => $request->integer('id'), 'shipped' => $request->boolean('shipped')]),
            ShippedUpdateResult::NotFound => response()->json(['message' => $result->message(AdminListType::Purchase)], 404),
            ShippedUpdateResult::Canceled => response()->json(['message' => $result->message(AdminListType::Purchase)], 409),
        };

        Log::debug('AdminPurchaseController@update end');

        return $response;
    }

    /**
     * 注文の取消（成功しても失敗しても、取消の前に見ていた検索条件・ページの一覧に戻り、案内かエラーを検索欄の下に表示する）
     *
     * 存在・発送済み・取消済みの判定と、銀行振込の Stripe の支払いの取消は、ロックを取る AdminOrderCancelService だけで行う。エラーの文言は OrderCancelResponder にまとめる。
     */
    public function cancel(AdminPurchaseCancelRequest $request, AdminOrderCancelService $service, OrderCancelResponder $responder): RedirectResponse
    {
        Log::debug('AdminPurchaseController@cancel start');

        $response = $responder(AdminListType::Purchase, $service->cancel(AdminListType::Purchase, $request->integer('id')));

        Log::debug('AdminPurchaseController@cancel end');

        return $response;
    }

    /**
     * 一覧のビュー（検索結果と、検索欄・エクスポートに戻す検索条件）
     *
     * @param  array{shipped: string, date: string, name: string}  $conditions
     */
    private function listView(AdminPurchaseService $service, array $conditions): View
    {
        $records = $service->search($conditions)->withQueryString();

        return view('admin.orders', [
            'purchases' => $records,
            'conditions' => $conditions,
            'exportCount' => $service->exportCount($conditions, $records->total()),
        ]);
    }
}
