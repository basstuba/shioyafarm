<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemDeleteRequest;
use App\Http\Requests\ItemStoreRequest;
use App\Http\Requests\ItemUpdateRequest;
use App\Models\Item;
use App\Services\AdminItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の掲載商品（一覧・登録・修正・削除。一覧は検索・ページ送りはなく、全件を表示する）
 *
 * 詳細のモーダルは Livewire の AdminItemDetail が表示する。登録・修正・削除のあとは一覧に戻り、成功の案内は flash の list_status、
 * 修正・削除の対象の商品が見つからないときの案内は errors（item・delete）で、一覧の表の上に出す。
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
            : $redirect->withErrors(['delete' => Item::NOT_FOUND_MESSAGE]);

        Log::debug('AdminItemController@delete end');

        return $response;
    }

    /**
     * 掲載商品登録ページ
     */
    public function create(): View
    {
        Log::debug('AdminItemController@create start');

        $view = view('admin.item_create');

        Log::debug('AdminItemController@create end');

        return $view;
    }

    /**
     * 商品の登録（成功したら一覧に戻り、緑の案内を出す。入力の誤り・重複は登録ページに戻して赤字で案内する）
     */
    public function store(ItemStoreRequest $request, AdminItemService $service): RedirectResponse
    {
        Log::debug('AdminItemController@store start');

        $service->create($request->toInput());
        $response = redirect()->route('admin.items.index')->with('list_status', '商品を登録しました。');

        Log::debug('AdminItemController@store end');

        return $response;
    }

    /**
     * 掲載商品修正ページ（削除済み・存在しない商品は一覧に戻し、赤字で「商品が見つかりません。」）
     */
    public function edit(string $item_id, AdminItemService $service): View|RedirectResponse
    {
        Log::debug('AdminItemController@edit start');

        $item = $service->findForEdit((int) $item_id);
        $response = $item === null
            ? redirect()->route('admin.items.index')->withErrors(['item' => Item::NOT_FOUND_MESSAGE])
            : view('admin.item_edit', ['item' => $item]);

        Log::debug('AdminItemController@edit end');

        return $response;
    }

    /**
     * 商品の修正（成功したら一覧に戻り、緑の案内を出す。入力の誤り・重複・予約済みの数より小さい上限は修正ページに戻して赤字で案内する。
     * 削除済み・存在しない商品は、何も変えずに一覧に戻し、赤字で「商品が見つかりません。」）
     */
    public function update(ItemUpdateRequest $request, AdminItemService $service): RedirectResponse
    {
        Log::debug('AdminItemController@update start');

        $redirect = redirect()->route('admin.items.index');
        $response = $service->update($request->integer('id'), $request->toInput())
            ? $redirect->with('list_status', '商品を修正しました。')
            : $redirect->withErrors(['item' => Item::NOT_FOUND_MESSAGE]);

        Log::debug('AdminItemController@update end');

        return $response;
    }
}
