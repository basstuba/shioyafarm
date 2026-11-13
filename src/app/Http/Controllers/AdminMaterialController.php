<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialDeleteRequest;
use App\Http\Requests\MaterialRequest;
use App\Http\Requests\MaterialUpdateRequest;
use App\Models\Material;
use App\Services\AdminMaterialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の資材（一覧・追加・修正・削除。すべて設計書にない追加機能。検索・ページ送りはなく、全件を表示する）
 *
 * 追加・修正は、一覧のページ内のモーダルで行う。入力の誤りは、モーダルを開いたままの一覧に戻して項目ごとに赤字で案内する。
 * 追加・修正・削除のあとは一覧に戻り、成功の案内は flash の list_status、
 * 修正・削除の対象の資材が見つからないときの案内は errors（material・delete）で、一覧の表の上に出す。
 */
class AdminMaterialController extends Controller
{
    /**
     * 資材一覧（全件を登録した順に表示する）
     */
    public function index(AdminMaterialService $service): View
    {
        Log::debug('AdminMaterialController@index start');

        $view = view('admin.materials', ['materials' => $service->list()]);

        Log::debug('AdminMaterialController@index end');

        return $view;
    }

    /**
     * 資材の追加（成功したら一覧に戻り、緑の案内を出す）
     */
    public function store(MaterialRequest $request, AdminMaterialService $service): RedirectResponse
    {
        Log::debug('AdminMaterialController@store start');

        $service->create($request->toInput());
        $response = redirect()->route('admin.materials.index')->with('list_status', '資材を追加しました。');

        Log::debug('AdminMaterialController@store end');

        return $response;
    }

    /**
     * 資材の修正（成功したら一覧に戻り、緑の案内を出す。存在しない資材は、何も変えずに一覧に戻し、赤字で「資材が見つかりません。」）
     */
    public function update(MaterialUpdateRequest $request, AdminMaterialService $service): RedirectResponse
    {
        Log::debug('AdminMaterialController@update start');

        $redirect = redirect()->route('admin.materials.index');
        $response = $service->update($request->integer('id'), $request->toInput())
            ? $redirect->with('list_status', '資材を修正しました。')
            : $redirect->withErrors(['material' => Material::NOT_FOUND_MESSAGE]);

        Log::debug('AdminMaterialController@update end');

        return $response;
    }

    /**
     * 資材の物理削除（成功しても見つからなくても一覧に戻り、案内かエラーを表の上に表示する。成功の案内は flash の list_status）
     */
    public function delete(MaterialDeleteRequest $request, AdminMaterialService $service): RedirectResponse
    {
        Log::debug('AdminMaterialController@delete start');

        $redirect = redirect()->route('admin.materials.index');
        $response = $service->delete($request->integer('id'))
            ? $redirect->with('list_status', '資材を削除しました。')
            : $redirect->withErrors(['delete' => Material::NOT_FOUND_MESSAGE]);

        Log::debug('AdminMaterialController@delete end');

        return $response;
    }
}
