<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminPurchaseUpdateRequest;
use App\Http\Requests\PurchaseSearchRequest;
use App\Services\AdminPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の注文一覧（表示・検索・発送済みの更新）
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
     */
    public function update(AdminPurchaseUpdateRequest $request, AdminPurchaseService $service): JsonResponse
    {
        Log::debug('AdminPurchaseController@update start');

        $service->updateShipped($request->integer('id'), $request->boolean('shipped'));

        Log::debug('AdminPurchaseController@update end');

        return response()->json(['id' => $request->integer('id'), 'shipped' => $request->boolean('shipped')]);
    }

    /**
     * 一覧のビュー（検索結果と、検索欄・エクスポートに戻す検索条件）
     *
     * @param  array{shipped: string, date: string, name: string}  $conditions
     */
    private function listView(AdminPurchaseService $service, array $conditions): View
    {
        return view('admin.orders', [
            'purchases' => $service->search($conditions)->withQueryString(),
            'conditions' => $conditions,
        ]);
    }
}
