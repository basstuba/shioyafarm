<?php

namespace App\Http\Requests;

use App\Support\MailTarget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 管理画面のメール送信の入力（メールタイトル・メール本文）
 *
 * 宛先は、フォームの hidden で送られる宛先の種類（type）とID（id）からサーバーで引き直す（メールアドレスは受け取らない。email を足されても使わない）。
 * 宛先の種類・IDの形が正しくないときは、検証の前に404にする（判定は MailTarget）。検証エラーのときは、そのメール作成ページに入力を残して戻す。
 */
class MailRequest extends FormRequest
{
    /** メールタイトルの項目名 */
    public const string TITLE = 'mail_title';

    /** メール本文の項目名（bootstrap/app.php で TrimStrings の対象から外している。他のフォームの項目と重ならない名前にする） */
    public const string BODY = 'mail_body';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 宛先の種類・IDの形が正しくなければ404にし（存在するかの確認は AdminMailService）、空白だけのメール本文を空と同じ扱い（必須のエラー）にする
     *
     * メール本文（mail_body）は、先頭の字下げ（全角スペースを含む）を送れるよう、項目名で TrimStrings の対象から外している（bootstrap/app.php）。
     * メールタイトル（mail_title）は他の入力と同じく TrimStrings が前後の空白を取り除く。
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $this->target();

        $body = $this->input(self::BODY);

        if (is_string($body) && Str::trim($body) === '') {
            $this->merge([self::BODY => null]);
        }
    }

    /**
     * 検証エラーのときは、宛先のメール作成ページに戻す（直前のURLに依存しない。戻り先は作成ページのURLのクエリに持たせる）
     */
    #[Override]
    protected function getRedirectUrl(): string
    {
        return $this->target()->createUrl($this->backUrl());
    }

    /**
     * 宛先の種類とID（hidden の type・id）。形が正しくなければ404
     *
     * @throws NotFoundHttpException
     */
    public function target(): MailTarget
    {
        return MailTarget::fromInput($this->input('type'), $this->input('id')) ?? throw new NotFoundHttpException;
    }

    /**
     * 戻り先として持ち回っている一覧のURL（hidden の back。検証は MailTarget で行う）
     */
    public function backUrl(): mixed
    {
        return $this->input('back');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            self::TITLE => 'required|string|max:255',
            self::BODY => 'required|string|max:10000',
        ];
    }

    /**
     * バリデーションエラーメッセージ
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'mail_title.required' => 'メールタイトルを入力してください',
            'mail_title.string' => 'メールタイトルは文字で入力してください',
            'mail_title.max' => 'メールタイトルは255文字以内で入力してください',
            'mail_body.required' => 'メール本文を入力してください',
            'mail_body.string' => 'メール本文は文章で入力してください',
            'mail_body.max' => 'メール本文は10000文字以内で入力してください',
        ];
    }
}
