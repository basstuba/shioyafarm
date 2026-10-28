<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse as FortifyFailedPasswordResetLinkRequestResponse;
use Override;

/**
 * パスワード再設定メールを送れなかったときの応答
 *
 * 会員が見つからない（未登録・退会済み）ときと、1分以内の再送信（throttle）のときは、
 * メールを送れたときと同じ応答を返し、アカウントの存在を漏らさない。それ以外は標準の応答にする。
 */
class FailedPasswordResetLinkRequestResponse extends FortifyFailedPasswordResetLinkRequestResponse
{
    /**
     * 応答を作る
     *
     * @param  Request  $request
     */
    #[Override]
    public function toResponse($request)
    {
        if (in_array($this->status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => Password::RESET_LINK_SENT])->toResponse($request);
        }

        return parent::toResponse($request);
    }
}
