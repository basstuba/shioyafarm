<?php

/**
 * 信頼するプロキシ（リバースプロキシ・ロードバランサー）の設定
 *
 * Laravel の TrustProxies ミドルウェア（グローバルミドルウェアに最初から入っている）が、リクエストのたびに config('trustedproxy.proxies') を読む。
 * bootstrap/app.php の $middleware->trustProxies() は設定の読み込み前に実行されるため、config() が使えない。そのため環境変数はここで読む。
 */
return [
    /**
     * 信頼するプロキシの IP アドレス・CIDR（環境変数 TRUSTED_PROXIES。カンマ区切り。例：172.16.0.0/12,10.0.0.1）。
     * `*` を指定すると、直接つながってきた相手（REMOTE_ADDR）をすべて信頼する。
     * 未設定・空のときは null で、X-Forwarded-For などのヘッダーは信頼しない（開発・E2E・テストと同じ動き）。
     * 信頼したプロキシからのリクエストだけ、$request->ip()（回数制限が数えるIP）・$request->isSecure() が X-Forwarded-* ヘッダーに従う。
     */
    'proxies' => is_string($proxies = env('TRUSTED_PROXIES')) && trim($proxies) !== '' ? trim($proxies) : null,
];
