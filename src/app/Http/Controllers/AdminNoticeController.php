<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoticeDeleteRequest;
use App\Http\Requests\NoticeRequest;
use App\Http\Requests\NoticeSearchRequest;
use App\Http\Requests\NoticeUpdateRequest;
use App\Models\Notice;
use App\Services\AdminNoticeService;
use App\Support\AdminListUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面のお知らせ（一覧・検索・作成・修正・削除。修正・削除は設計書にない追加機能）
 *
 * 詳細のモーダルは Livewire の AdminNoticeDetail が表示する。作成・修正・削除のあとは一覧に戻り、成功の案内は flash の list_status、
 * 修正・削除の対象のお知らせが見つからないときの案内は errors（notice・delete）で、検索欄の下に出す。
 */
class AdminNoticeController extends Controller
{
    /**
     * お知らせ一覧（検索条件なしの全件）
     */
    public function index(AdminNoticeService $service): View
    {
        Log::debug('AdminNoticeController@index start');

        $view = $this->listView($service, ['date' => '']);

        Log::debug('AdminNoticeController@index end');

        return $view;
    }

    /**
     * お知らせ一覧の検索（作成日。ページ送りでも検索条件を保つ）
     */
    public function search(NoticeSearchRequest $request, AdminNoticeService $service): View
    {
        Log::debug('AdminNoticeController@search start');

        $view = $this->listView($service, $request->conditions());

        Log::debug('AdminNoticeController@search end');

        return $view;
    }

    /**
     * お知らせ作成ページ
     */
    public function create(): View
    {
        Log::debug('AdminNoticeController@create start');

        $view = view('admin.notice_create');

        Log::debug('AdminNoticeController@create end');

        return $view;
    }

    /**
     * お知らせの作成（成功したら一覧に戻り、緑の案内を出す。入力の誤りは作成ページに戻して項目ごとに赤字で案内する）
     */
    public function store(NoticeRequest $request, AdminNoticeService $service): RedirectResponse
    {
        Log::debug('AdminNoticeController@store start');

        $service->create($request->toInput());
        $response = redirect()->route('admin.notices.index')->with('list_status', 'お知らせを作成しました。');

        Log::debug('AdminNoticeController@store end');

        return $response;
    }

    /**
     * お知らせ修正ページ（存在しないお知らせは一覧に戻し、赤字で「お知らせが見つかりません。」）
     */
    public function edit(string $notice_id, AdminNoticeService $service): View|RedirectResponse
    {
        Log::debug('AdminNoticeController@edit start');

        $notice = $service->findForEdit((int) $notice_id);
        $response = $notice === null
            ? redirect()->route('admin.notices.index')->withErrors(['notice' => Notice::NOT_FOUND_MESSAGE])
            : view('admin.notice_edit', ['notice' => $notice]);

        Log::debug('AdminNoticeController@edit end');

        return $response;
    }

    /**
     * お知らせの修正（成功したら一覧に戻り、緑の案内を出す。入力の誤りは修正ページに戻して赤字で案内する。
     * 存在しないお知らせは、何も変えずに一覧に戻し、赤字で「お知らせが見つかりません。」）
     */
    public function update(NoticeUpdateRequest $request, AdminNoticeService $service): RedirectResponse
    {
        Log::debug('AdminNoticeController@update start');

        $redirect = redirect()->route('admin.notices.index');
        $response = $service->update($request->integer('id'), $request->toInput())
            ? $redirect->with('list_status', 'お知らせを修正しました。')
            : $redirect->withErrors(['notice' => Notice::NOT_FOUND_MESSAGE]);

        Log::debug('AdminNoticeController@update end');

        return $response;
    }

    /**
     * お知らせの物理削除（成功しても見つからなくても、削除の前に見ていた検索条件・ページの一覧に戻り、案内かエラーを検索欄の下に表示する。成功の案内は flash の list_status）
     */
    public function delete(NoticeDeleteRequest $request, AdminNoticeService $service): RedirectResponse
    {
        Log::debug('AdminNoticeController@delete start');

        $redirect = redirect(AdminListUrl::previousOrIndex('admin.notices.index', 'admin.notices.search'));
        $response = $service->delete($request->integer('id'))
            ? $redirect->with('list_status', 'お知らせを削除しました。')
            : $redirect->withErrors(['delete' => Notice::NOT_FOUND_MESSAGE]);

        Log::debug('AdminNoticeController@delete end');

        return $response;
    }

    /**
     * 一覧のビュー（検索結果と、検索欄に戻す検索条件）
     *
     * @param  array{date: string}  $conditions
     */
    private function listView(AdminNoticeService $service, array $conditions): View
    {
        return view('admin.notices', [
            'notices' => $service->search($conditions)->withQueryString(),
            'conditions' => $conditions,
        ]);
    }
}
