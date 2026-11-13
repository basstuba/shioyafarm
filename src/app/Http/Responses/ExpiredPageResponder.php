<?php

namespace App\Http\Responses;

use App\Enums\AdminListType;
use App\Enums\MailRecipientType;
use App\Http\Requests\MailRequest;
use App\Services\AdminTwoFactorStore;
use App\Support\AdminListUrl;
use App\Support\AdminUserListUrl;
use App\Support\MailTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CSRFトークンの不一致（TokenMismatchException。描画の前に 419 の HttpException になる）のとき、ログイン・ログアウトだけは、案内つきで元のページへ戻す
 *
 * 同じブラウザで別の窓のログイン・ログアウトをしたときや、時間が経ったときにトークンが古くなる。
 * 対象は会員のログイン（login.store）・管理者のログイン（admin.login.store）・管理者の確認コードの送信と再送（admin.two-factor.store・admin.two-factor.resend。入力待ちが残っていれば、それぞれの案内つきで確認コードの入力ページへ、なければログインページへ戻す）・管理者のログアウト（admin.logout）・会員の物理削除（admin.users.delete）・注文と予約の取消（admin.purchase.cancel・admin.reservations.cancel）・管理者のパスワード再設定の2つの送信（admin.password.email・admin.password.update）・管理画面のメール送信（admin.mail.send）・掲載商品の登録と修正（admin.items.store・admin.items.update）・お知らせの作成・修正・削除（admin.notices.store・admin.notices.update・admin.notices.delete）・資材の追加・修正・削除（admin.materials.store・admin.materials.update・admin.materials.delete）だけで、
 * それ以外のリクエスト（JSONを期待するリクエスト、Livewireの更新を含む）は null を返して従来どおり419のページにする。
 * 案内は flash の status に入れ、各ページがフォームの上に表示する（会員の物理削除と、注文・予約の取消は、実行できなかった知らせなので errors の delete・cancel に入れ、一覧の検索欄の下に赤字で出す。メール送信は errors の send に入れ、メール作成ページに入力を残して赤字で出す。掲載商品の登録・修正は errors の form に入れ、登録・修正ページに画像以外の入力を残してボタンの上に赤字で出す。お知らせの作成・修正も errors の form に入れ、作成・修正ページに入力を残してボタンの上に赤字で出す。お知らせの削除は errors の delete に入れ、削除の前に見ていたお知らせ一覧（検索条件・ページ）の検索欄の下に赤字で出す。資材の追加・修正は errors の form に入れ、入力を残したまま資材一覧のモーダルを開いて赤字で出す。資材の削除は errors の delete に入れ、資材一覧の表の上に赤字で出す）。
 */
class ExpiredPageResponder
{
    /** ログインのときの案内 */
    public const string LOGIN_MESSAGE = 'ページの有効期限が切れました。もう一度ログインしてください。';

    /** ログアウトのときの案内 */
    public const string LOGOUT_MESSAGE = 'ページの有効期限が切れました。もう一度ログアウトしてください。';

    /** 会員の物理削除のときの案内（削除できなかった知らせなので、errors の delete に入れる） */
    public const string USER_DELETE_MESSAGE = 'ページの有効期限が切れました。もう一度削除してください。';

    /** 注文・予約の取消のときの案内（実行できなかった知らせなので、errors の cancel に入れる） */
    public const string ORDER_CANCEL_MESSAGE = 'ページの有効期限が切れました。もう一度取り消してください。';

    /** 管理者の確認コードの送信のとき、入力待ちが残っているときの案内（コードは置き換えず、届いているコードをそのまま使える） */
    public const string TWO_FACTOR_MESSAGE = 'ページの有効期限が切れました。もう一度入力してください。';

    /** 管理者の確認コードの再送のとき、入力待ちが残っているときの案内（再送はされていないので、再送を押し直してもらう） */
    public const string TWO_FACTOR_RESEND_MESSAGE = 'ページの有効期限が切れました。もう一度「コードを再送する」を押してください。';

    /** 管理者のパスワード再設定のときの案内 */
    public const string ADMIN_PASSWORD_RESET_MESSAGE = 'ページの有効期限が切れました。もう一度お試しください。';

    /** 管理画面のメール送信のときの案内（送信できなかった知らせなので、errors の send に入れる） */
    public const string MAIL_SEND_MESSAGE = 'ページの有効期限が切れました。もう一度送信してください。';

    /** 掲載商品の登録のときの案内（登録できなかった知らせなので、errors の form に入れる） */
    public const string ITEM_STORE_MESSAGE = 'ページの有効期限が切れました。もう一度登録してください。';

    /** 掲載商品の修正のときの案内（修正できなかった知らせなので、errors の form に入れる。修正する商品が決まらないときは一覧の errors の item に入れる） */
    public const string ITEM_UPDATE_MESSAGE = 'ページの有効期限が切れました。もう一度修正してください。';

    /** お知らせの作成のときの案内（作成できなかった知らせなので、errors の form に入れる） */
    public const string NOTICE_STORE_MESSAGE = 'ページの有効期限が切れました。もう一度作成してください。';

    /** お知らせの修正のときの案内（修正できなかった知らせなので、errors の form に入れる。修正するお知らせが決まらないときは一覧の errors の notice に入れる） */
    public const string NOTICE_UPDATE_MESSAGE = 'ページの有効期限が切れました。もう一度修正してください。';

    /** お知らせの削除のときの案内（削除できなかった知らせなので、errors の delete に入れる） */
    public const string NOTICE_DELETE_MESSAGE = 'ページの有効期限が切れました。もう一度削除してください。';

    /** 資材の追加のときの案内（追加できなかった知らせなので、errors の form に入れ、一覧のモーダルを開いて出す） */
    public const string MATERIAL_STORE_MESSAGE = 'ページの有効期限が切れました。もう一度追加してください。';

    /** 資材の修正のときの案内（修正できなかった知らせなので、errors の form に入れ、一覧のモーダルを開いて出す。修正する資材が決まらないときは一覧の errors の material に入れる） */
    public const string MATERIAL_UPDATE_MESSAGE = 'ページの有効期限が切れました。もう一度修正してください。';

    /** 資材の削除のときの案内（削除できなかった知らせなので、errors の delete に入れる） */
    public const string MATERIAL_DELETE_MESSAGE = 'ページの有効期限が切れました。もう一度削除してください。';

    /**
     * 戻す応答を作る（対象でなければ null）
     */
    public function __invoke(HttpException $e, Request $request): ?Response
    {
        // Laravel は TokenMismatchException を、描画の前に 419 の HttpException に変える
        if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return null;
        }

        return match ($request->route()?->getName()) {
            'login.store' => $this->backWithEmail($request, 'login', self::LOGIN_MESSAGE),
            'admin.login.store' => $this->backWithEmail($request, 'admin.login', self::LOGIN_MESSAGE),
            'admin.two-factor.store' => $this->backFromTwoFactor($request, self::TWO_FACTOR_MESSAGE),
            'admin.two-factor.resend' => $this->backFromTwoFactor($request, self::TWO_FACTOR_RESEND_MESSAGE),
            'admin.logout' => $this->backFromLogout(),
            'admin.users.delete' => $this->backFromUserDelete(),
            'admin.purchase.cancel' => $this->backFromOrderCancel(AdminListType::Purchase),
            'admin.reservations.cancel' => $this->backFromOrderCancel(AdminListType::Reservation),
            'admin.mail.send' => $this->backFromMailSend($request),
            'admin.items.store' => $this->backFromItemStore($request),
            'admin.items.update' => $this->backFromItemUpdate($request),
            'admin.notices.store' => $this->backFromNoticeStore($request),
            'admin.notices.update' => $this->backFromNoticeUpdate($request),
            'admin.notices.delete' => $this->backFromNoticeDelete(),
            'admin.materials.store' => $this->backFromMaterialStore($request),
            'admin.materials.update' => $this->backFromMaterialUpdate($request),
            'admin.materials.delete' => $this->backFromMaterialDelete(),
            'admin.password.email' => $this->backWithEmail($request, 'admin.password.request', self::ADMIN_PASSWORD_RESET_MESSAGE),
            'admin.password.update' => $this->backToAdminResetPassword($request),
            default => null,
        };
    }

    /**
     * 指定のページ（ログインページ・再設定メールの送信ページ）へ、案内つきで戻す（メールアドレスは残し、パスワードは残さない）
     */
    private function backWithEmail(Request $request, string $route, string $message): Response
    {
        $email = $request->input('email');

        return redirect()->route($route)
            ->withInput(['email' => is_string($email) ? $email : ''])
            ->with('status', $message);
    }

    /**
     * 確認コードの入力待ちが残っていれば確認コードの入力ページへ（コードは送り直さず、メールも送らない）、残っていなければログインページへ戻す
     */
    private function backFromTwoFactor(Request $request, string $message): Response
    {
        // 入力待ちがあるかどうかだけ見る（DBの確認やセッションの書き換えはしない。戻り先の確認コードのページが、改めて確かめる）
        if ((new AdminTwoFactorStore($request->session()))->get() !== null) {
            return redirect()->route('admin.two-factor')->with('status', $message);
        }

        return redirect()->route('admin.login')->with('status', self::LOGIN_MESSAGE);
    }

    /**
     * 管理者がまだログイン中なら管理画面のトップへ、そうでなければログインページへ戻す
     */
    private function backFromLogout(): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.home')->with('status', self::LOGOUT_MESSAGE);
    }

    /**
     * 管理者がまだログイン中なら削除の前に見ていた会員一覧へ（削除はされていない）、そうでなければログインページへ戻す
     */
    private function backFromUserDelete(): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect(AdminUserListUrl::previousOrIndex())->withErrors(['delete' => self::USER_DELETE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら取消の前に見ていた注文一覧・予約一覧へ（取消はされていない）、そうでなければログインページへ戻す
     */
    private function backFromOrderCancel(AdminListType $type): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect(AdminListUrl::previousOrIndex($type->listRoute(), $type->searchRoute()))->withErrors(['cancel' => self::ORDER_CANCEL_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、送信フォームの宛先（type・id）のメール作成ページへ入力（タイトル・本文・戻り先）を残して戻す（メールは送らない）。
     * 宛先の種類・IDが不正なときは、種類だけ有効ならその種類の一覧へ、種類も不正なら会員一覧へ戻す。管理者がログインしていなければログインページへ戻す
     */
    private function backFromMailSend(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $target = MailTarget::fromInput($request->input('type'), $request->input('id'));

        if ($target === null) {
            // 宛先が不正でも、種類だけ有効ならその種類の一覧へ戻す。種類も不正なら会員一覧へ
            $type = MailTarget::typeFromInput($request->input('type')) ?? MailRecipientType::User;

            return redirect()->route($type->indexRoute())->withErrors(['send' => self::MAIL_SEND_MESSAGE]);
        }

        return redirect($target->createUrl($request->input('back')))
            ->withInput($request->only([MailRequest::TITLE, MailRequest::BODY, 'back']))
            ->withErrors(['send' => self::MAIL_SEND_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、掲載商品の登録ページへ画像以外の入力を残して戻す（登録はしない）。ログインしていなければログインページへ
     */
    private function backFromItemStore(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.items.create')
            ->withInput($request->except(['_token', 'image']))
            ->withErrors(['form' => self::ITEM_STORE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、送信フォームの商品（id）の修正ページへ画像以外の入力を残して戻す（更新はしない）。
     * 商品のIDが正しくないときは一覧へ戻す。管理者がログインしていなければログインページへ
     */
    private function backFromItemUpdate(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $id = $request->input('id');
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            return redirect()->route('admin.items.index')->withErrors(['item' => self::ITEM_UPDATE_MESSAGE]);
        }

        return redirect()->route('admin.items.edit', ['item_id' => $id])
            ->withInput($request->except(['_token', 'image']))
            ->withErrors(['form' => self::ITEM_UPDATE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、お知らせの作成ページへ入力を残して戻す（作成はしない）。ログインしていなければログインページへ
     */
    private function backFromNoticeStore(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.notices.create')
            ->withInput($request->except('_token'))
            ->withErrors(['form' => self::NOTICE_STORE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、送信フォームのお知らせ（id）の修正ページへ入力を残して戻す（更新はしない）。
     * お知らせのIDが正しくないときは一覧へ戻す。管理者がログインしていなければログインページへ
     */
    private function backFromNoticeUpdate(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $id = $request->input('id');
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            return redirect()->route('admin.notices.index')->withErrors(['notice' => self::NOTICE_UPDATE_MESSAGE]);
        }

        return redirect()->route('admin.notices.edit', ['notice_id' => $id])
            ->withInput($request->except('_token'))
            ->withErrors(['form' => self::NOTICE_UPDATE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら削除の前に見ていたお知らせ一覧へ（削除はされていない）、そうでなければログインページへ戻す
     */
    private function backFromNoticeDelete(): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect(AdminListUrl::previousOrIndex('admin.notices.index', 'admin.notices.search'))->withErrors(['delete' => self::NOTICE_DELETE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、資材一覧へ入力を残して戻す（追加はしない。一覧が、入力の残りから追加のモーダルを開く）。ログインしていなければログインページへ
     */
    private function backFromMaterialStore(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.materials.index')
            ->withInput($request->except(['_token', 'id']))
            ->withErrors(['form' => self::MATERIAL_STORE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら、送信フォームの資材（id）の修正のモーダルを開く形で、入力を残して資材一覧へ戻す（更新はしない）。
     * 資材のIDが正しくないときは、モーダルを開かずに案内だけを出す。管理者がログインしていなければログインページへ
     */
    private function backFromMaterialUpdate(Request $request): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $id = $request->input('id');
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            return redirect()->route('admin.materials.index')->withErrors(['material' => self::MATERIAL_UPDATE_MESSAGE]);
        }

        return redirect()->route('admin.materials.index')
            ->withInput($request->except('_token'))
            ->withErrors(['form' => self::MATERIAL_UPDATE_MESSAGE]);
    }

    /**
     * 管理者がまだログイン中なら資材一覧へ（削除はされていない）、そうでなければログインページへ戻す
     */
    private function backFromMaterialDelete(): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return redirect()->route('admin.materials.index')->withErrors(['delete' => self::MATERIAL_DELETE_MESSAGE]);
    }

    /**
     * 管理者の新しいパスワードの設定ページへ戻す（トークンとメールアドレスはリンクに戻し、パスワードは残さない。トークンがなければ送信ページへ）
     */
    private function backToAdminResetPassword(Request $request): Response
    {
        $token = $request->input('token');
        $email = $request->input('email');

        if (! is_string($token) || $token === '') {
            return $this->backWithEmail($request, 'admin.password.request', self::ADMIN_PASSWORD_RESET_MESSAGE);
        }

        return redirect()->route('admin.password.reset', ['token' => $token, 'email' => is_string($email) ? $email : ''])
            ->with('status', self::ADMIN_PASSWORD_RESET_MESSAGE);
    }
}
