<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Override;

/**
 * 管理者の確認コード（二段階認証）の入力のリクエスト
 *
 * 全角数字は半角にして受け付ける。未入力・形式違い（6桁の数字でない）は、ここで入力エラーにする（失敗回数には数えない）。
 * 入力エラーのときは確認コードの入力ページへ戻す。
 */
class AdminTwoFactorRequest extends FormRequest
{
    /**
     * 入力エラーのときの戻り先のルート名
     *
     * @var string
     */
    protected $redirectRoute = 'admin.two-factor';

    /**
     * 全角数字を半角にする（前後の空白は TrimStrings が取り除く。全角の空白は半角にしてから取り除く）
     */
    #[Override]
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => trim(mb_convert_kana($code, 'ns'))]);
        }
    }

    /**
     * バリデーションルール
     *
     * 配列などの文字列でない入力でも500にならないよう、先に string で検証する。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'regex:/\A\d{6}\z/'],
        ];
    }

    /**
     * エラーメッセージ
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'code.required' => trans('admin_two_factor.code_required'),
            'code.string' => trans('admin_two_factor.code_format'),
            'code.regex' => trans('admin_two_factor.code_format'),
        ];
    }
}
