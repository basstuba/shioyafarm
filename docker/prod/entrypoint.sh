#!/bin/sh
# app コンテナの起動スクリプト。php-fpm を起動するときだけ、MySQL の起動を待ち、キャッシュを作り、マイグレーションを流す。
# 起動のたびにデータを作り直すことはしない（migrate:fresh・db:seed は実行しない。データの作り直しは scripts/demo/reset.sh）。
set -eu

cd /var/www

if [ "${1:-}" = "php-fpm" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY が空です。docker/prod/app.env に設定してください（php artisan key:generate --show で作れます）。" >&2
        exit 1
    fi

    # MySQL の起動を待つ（最大約2分。compose の healthcheck とは別に、接続できることを確かめる）
    tries=0
    until php -r '
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE"));
        try {
            new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage().PHP_EOL);
            exit(1);
        }
    '; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "MySQL に接続できませんでした。" >&2
            exit 1
        fi
        echo "MySQL の起動を待っています（${tries}/60）..."
        sleep 2
    done

    # 画像は storage/app/public（ホストの置き場所を重ねたもの）から配信する
    [ -L public/storage ] || php artisan storage:link

    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # 未適用のマイグレーションだけを流す（既存のデータは消さない）
    php artisan migrate --force
fi

exec "$@"
