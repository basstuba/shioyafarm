<?php

/**
 * 公開デモ（ポートフォリオ用の公開サイト）の設定
 */
return [
    /**
     * デモの案内（公開側・管理画面のすべてのページの上部）を出すか。環境変数 DEMO_NOTICE（既定は出さない）
     * true・1・on・yes のときだけ出す（それ以外・未設定・空は出さない）。開発・E2E・テストでは出さない。
     */
    'notice' => filter_var(env('DEMO_NOTICE', false), FILTER_VALIDATE_BOOLEAN),
];
