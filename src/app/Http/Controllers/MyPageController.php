<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Services\AreaService;
use App\Services\MyPageService;
use App\Support\LoginUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * マイページコントローラ（登録情報の表示・変更・退会）
 *
 * お届け先住所・購入履歴・予約履歴の一覧と詳細は、Livewire コンポーネントが表示する。
 */
class MyPageController extends Controller
{
    /** 登録情報を変更したときのフラッシュメッセージ */
    public const string UPDATED_MESSAGE = '登録情報を変更しました';

    /** 何も変更せずに「変更する」を押したときのメッセージ（登録情報変更ページに表示する） */
    public const string NO_CHANGE_MESSAGE = '変更された項目はありません';

    /** 退会したときのフラッシュメッセージ（トップページに表示する） */
    public const string WITHDRAWN_MESSAGE = '退会手続きが完了しました';

    /**
     * マイページを表示する
     */
    public function index(Request $request): View
    {
        Log::debug('MyPageController index start');

        $user = LoginUser::require();

        Log::debug('MyPageController index end');

        return view('shop.my_page', compact('user'));
    }

    /**
     * 登録情報変更ページを表示する
     */
    public function edit(Request $request, AreaService $areaService): View
    {
        Log::debug('MyPageController edit start');

        $user = LoginUser::require();
        $prefectures = $areaService->getPrefectures();

        Log::debug('MyPageController edit end');

        return view('shop.user_edit', compact('user', 'prefectures'));
    }

    /**
     * 登録情報を更新して、マイページへ戻る（変更がないときは登録情報変更ページに戻る）
     */
    public function update(UserRequest $request, MyPageService $myPageService): RedirectResponse
    {
        Log::debug('MyPageController update start');

        $changed = $myPageService->updateProfile(LoginUser::require(), $request->validated());

        Log::debug('MyPageController update end');

        // 変更がないときは保存せず、登録情報変更ページに留まる
        if (! $changed) {
            return redirect()->route('shop.mypage.edit')->with('info_message', self::NO_CHANGE_MESSAGE);
        }

        return redirect()->route('shop.mypage.index')->with('flash_message', self::UPDATED_MESSAGE);
    }

    /**
     * 退会する（論理削除）。ログアウトしてセッションを破棄し、トップページへ移動する
     *
     * ほかの端末のセッションは、退会した会員が認証から外れるため、次のリクエストでログアウト状態になる。
     */
    public function delete(Request $request, MyPageService $myPageService): RedirectResponse
    {
        Log::debug('MyPageController delete start');

        $myPageService->withdraw(LoginUser::require());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::debug('MyPageController delete end');

        return redirect()->route('top.index')->with('flash_message', self::WITHDRAWN_MESSAGE);
    }
}
