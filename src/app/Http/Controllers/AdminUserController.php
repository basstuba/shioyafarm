<?php

namespace App\Http\Controllers;

use App\Enums\UserDeleteResult;
use App\Http\Requests\UserDeleteRequest;
use App\Http\Requests\UserSearchRequest;
use App\Services\AdminUserService;
use App\Support\AdminUserListUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面の会員一覧（表示・検索・退会済みの会員の物理削除）
 *
 * 詳細のモーダルは Livewire の AdminUserDetail が表示する。
 */
class AdminUserController extends Controller
{
    /**
     * 会員一覧（検索条件なしの全件）
     */
    public function index(AdminUserService $service): View
    {
        Log::debug('AdminUserController@index start');

        $view = $this->listView($service, ['status' => '', 'name' => '']);

        Log::debug('AdminUserController@index end');

        return $view;
    }

    /**
     * 会員一覧の検索（状態・氏名。ページ送りでも検索条件を保つ）
     */
    public function search(UserSearchRequest $request, AdminUserService $service): View
    {
        Log::debug('AdminUserController@search start');

        $view = $this->listView($service, $request->conditions());

        Log::debug('AdminUserController@search end');

        return $view;
    }

    /**
     * 退会済みの会員の物理削除（成功しても失敗しても、削除の前に見ていた検索条件・ページの一覧に戻り、案内かエラーを検索欄の下に表示する。成功の案内は flash の list_status で、layout の status（x-admin-status）には出さない）
     *
     * 退会しているかの判定は、ロックを取るリポジトリだけで行う。エラーの文言はここにまとめる。
     */
    public function delete(UserDeleteRequest $request, AdminUserService $service): RedirectResponse
    {
        Log::debug('AdminUserController@delete start');

        $redirect = redirect(AdminUserListUrl::previousOrIndex());
        $response = match ($service->delete($request->integer('id'))) {
            UserDeleteResult::Deleted => $redirect->with('list_status', '会員を削除しました。'),
            UserDeleteResult::HasUnshipped => $redirect->withErrors(['delete' => '未発送の注文・予約があるため削除できません。']),
            UserDeleteResult::NotWithdrawn => $redirect->withErrors(['delete' => '退会していない会員、または存在しない会員は削除できません。']),
            UserDeleteResult::Failed => $redirect->withErrors(['delete' => '削除できませんでした。時間をおいてもう一度お試しください。']),
        };

        Log::debug('AdminUserController@delete end');

        return $response;
    }

    /**
     * 一覧のビュー（検索結果と、検索欄に戻す検索条件）
     *
     * @param  array{status: string, name: string}  $conditions
     */
    private function listView(AdminUserService $service, array $conditions): View
    {
        return view('admin.users', [
            'users' => $service->search($conditions)->withQueryString(),
            'conditions' => $conditions,
        ]);
    }
}
