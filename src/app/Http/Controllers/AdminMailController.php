<?php

namespace App\Http\Controllers;

use App\Enums\MailRecipientType;
use App\Enums\MailSendResult;
use App\Http\Requests\MailRequest;
use App\Services\AdminMailService;
use App\Support\MailTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * 管理画面のメール作成・送信（会員宛て・お問い合わせへの返信）
 *
 * 作成ページを開いた時点の「前のURL」（来た一覧の検索条件・ページつき）を hidden の back で持ち回り、送信後と「一覧へ戻る」で使う。
 */
class AdminMailController extends Controller
{
    /**
     * 会員宛てのメール作成ページ（退会済みの会員も開ける。存在しない会員は404）
     */
    public function createUserMail(string $user_id, Request $request, AdminMailService $service): View
    {
        Log::debug('AdminMailController@createUserMail start');

        $view = $this->createView(MailRecipientType::User, $user_id, $request, $service);

        Log::debug('AdminMailController@createUserMail end');

        return $view;
    }

    /**
     * お問い合わせへの返信のメール作成ページ（存在しないお問い合わせは404）
     */
    public function createInquiryMail(string $inquiry_id, Request $request, AdminMailService $service): View
    {
        Log::debug('AdminMailController@createInquiryMail start');

        $view = $this->createView(MailRecipientType::Inquiry, $inquiry_id, $request, $service);

        Log::debug('AdminMailController@createInquiryMail end');

        return $view;
    }

    /**
     * メールを送る
     *
     * 成功したら来た一覧に戻し、検索欄の下に「メールを送信しました。」を出す（flash の list_status）。
     * 失敗したらメール作成ページ（戻り先つき）に入力を残して戻り、赤字で案内する。存在しない宛先は404。
     */
    public function send(MailRequest $request, AdminMailService $service): RedirectResponse
    {
        Log::debug('AdminMailController@send start');

        $target = $request->target();

        $response = match ($service->send($target, $request->string(MailRequest::TITLE)->toString(), $request->string(MailRequest::BODY)->toString())) {
            MailSendResult::Sent, MailSendResult::AlreadySent => redirect($target->listUrl($request->backUrl()))->with('list_status', 'メールを送信しました。'),
            MailSendResult::NotFound => abort(404),
            MailSendResult::Failed => redirect($target->createUrl($request->backUrl()))->withInput()->withErrors(['send' => 'メールを送信できませんでした。時間をおいて、もう一度お試しください。']),
        };

        Log::debug('AdminMailController@send end');

        return $response;
    }

    /**
     * メール作成ページのビュー（宛先を引き直す。形が正しくない・存在しなければ404）
     *
     * 戻り先（来た一覧）は、入力エラー・送信失敗で戻ってきたときの入力 → 作成ページのURLのクエリの back（戻ったあとの再読み込み） → 直前のページの順に、
     * その種類の一覧・検索結果として使えるものを採用する（直前のページは、戻ってきたときは作成ページ自身になる）。どれも使えなければ一覧の1ページ目。
     */
    private function createView(MailRecipientType $type, string $id, Request $request, AdminMailService $service): View
    {
        $target = MailTarget::fromInput($type->value, $id) ?? abort(404);
        $recipient = $service->findRecipient($target) ?? abort(404);

        return view('admin.mail_create', [
            'recipient' => $recipient,
            'back' => $target->listUrl($request->old('back'), $request->query('back'), url()->previous()),
        ]);
    }
}
