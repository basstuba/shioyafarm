<?php

namespace App\Enums;

/**
 * 管理者のパスワード再設定の結果（入力の誤りは ValidationException で返す）
 */
enum AdminPasswordResetResult
{
    /** パスワードを再設定できた */
    case Reset;

    /** リンクが無効（使用済み・期限切れ・改ざん・管理者でないメールアドレス） */
    case InvalidLink;
}
