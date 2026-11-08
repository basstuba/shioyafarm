<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminInquiryUpdateRequest;
use App\Http\Requests\InquirySearchRequest;
use App\Services\AdminInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面のお問い合わせ一覧（表示・検索・対応済みの更新）
 *
 * 詳細のモーダルは Livewire の AdminInquiryDetail が表示する。
 */
class AdminInquiryController extends Controller
{
    /**
     * お問い合わせ一覧（検索条件なしの全件）
     */
    public function index(AdminInquiryService $service): View
    {
        Log::debug('AdminInquiryController@index start');

        $view = $this->listView($service, ['addressed' => '', 'date' => '']);

        Log::debug('AdminInquiryController@index end');

        return $view;
    }

    /**
     * お問い合わせ一覧の検索（対応状況・問い合わせ日。ページ送りでも検索条件を保つ）
     */
    public function search(InquirySearchRequest $request, AdminInquiryService $service): View
    {
        Log::debug('AdminInquiryController@search start');

        $view = $this->listView($service, $request->conditions());

        Log::debug('AdminInquiryController@search end');

        return $view;
    }

    /**
     * 対応済みかどうかの更新（チェックを押した瞬間に Alpine から送られる。JSON を返す）
     *
     * 存在しないお問い合わせ（検証のあとに削除されたときなど）は 404 を返す（一覧のチェックと行の色を戻す）。お客様にメールは送らない。
     */
    public function update(AdminInquiryUpdateRequest $request, AdminInquiryService $service): JsonResponse
    {
        Log::debug('AdminInquiryController@update start');

        $id = $request->integer('id');
        $addressed = $request->boolean('addressed');
        $response = $service->updateAddressed($id, $addressed)
            ? response()->json(['id' => $id, 'addressed' => $addressed])
            : response()->json(['message' => 'お問い合わせが見つかりません。'], 404);

        Log::debug('AdminInquiryController@update end');

        return $response;
    }

    /**
     * 一覧のビュー（検索結果と、検索欄に戻す検索条件）
     *
     * @param  array{addressed: string, date: string}  $conditions
     */
    private function listView(AdminInquiryService $service, array $conditions): View
    {
        return view('admin.inquiries', [
            'inquiries' => $service->search($conditions)->withQueryString(),
            'conditions' => $conditions,
        ]);
    }
}
