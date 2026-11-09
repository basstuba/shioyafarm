<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemDeleteRequest;
use App\Services\AdminItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の掲載商品一覧（一覧・削除。検索・ページ送りはなく、全件を表示する）
 *
 * 詳細のモーダルは Livewire の AdminItemDetail が表示する。登録・修正は別のコントローラーメソッド（次のブランチで追加）。
 */
class AdminItemController extends Controller
{
    /**
     * 掲載商品一覧（削除していない商品の全件）
     */
    public function index(AdminItemService $service): View
    {
        Log::debug('AdminItemController@index start');

        $view = view('admin.items', ['items' => $service->listItems()]);

        Log::debug('AdminItemController@index end');

        return $view;
    }

    /**
     * 商品の論理削除（成功しても見つからなくても一覧に戻り、案内かエラーを表の上に表示する。成功の案内は flash の list_status）
     */
    public function delete(ItemDeleteRequest $request, AdminItemService $service): RedirectResponse
    {
        Log::debug('AdminItemController@delete start');

        $redirect = redirect()->route('admin.items.index');
        $response = $service->delete($request->integer('id'))
            ? $redirect->with('list_status', '商品を削除しました。')
            : $redirect->withErrors(['delete' => ItemDeleteRequest::NOT_FOUND_MESSAGE]);

        Log::debug('AdminItemController@delete end');

        return $response;
    }
}
