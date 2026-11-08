<?php

namespace App\Http\Responses;

use App\Enums\AdminListType;
use App\Enums\MailRecipientType;
use App\Http\Requests\MailRequest;
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
 * 対象は会員のログイン（login.store）・管理者のログイン（admin.login.store）・管理者のログアウト（admin.logout）・会員の物理削除（admin.users.delete）・注文と予約の取消（admin.purchase.cancel・admin.reservations.cancel）・管理者のパスワード再設定の2つの送信（admin.password.email・admin.password.update）・管理画面のメール送信（admin.mail.send）だけで、
 * それ以外のリクエスト（JSONを期待するリクエスト、Livewireの更新を含む）は null を返して従来どおり419のページにする。
 * 案内は flash の status に入れ、各ページがフォームの上に表示する（会員の物理削除と、注文・予約の取消は、実行できなかった知らせなので errors の delete・cancel に入れ、一覧の検索欄の下に赤字で出す。メール送信は errors の send に入れ、メール作成ページに入力を残して赤字で出す）。
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

    /** 管理者のパスワード再設定のときの案内 */
    public const string ADMIN_PASSWORD_RESET_MESSAGE = 'ページの有効期限が切れました。もう一度お試しください。';

    /** 管理画面のメール送信のときの案内（送信できなかった知らせなので、errors の send に入れる） */
    public const string MAIL_SEND_MESSAGE = 'ページの有効期限が切れました。もう一度送信してください。';

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
            'admin.logout' => $this->backFromLogout(),
            'admin.users.delete' => $this->backFromUserDelete(),
            'admin.purchase.cancel' => $this->backFromOrderCancel(AdminListType::Purchase),
            'admin.reservations.cancel' => $this->backFromOrderCancel(AdminListType::Reservation),
            'admin.mail.send' => $this->backFromMailSend($request),
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
