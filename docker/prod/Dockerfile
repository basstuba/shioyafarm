# 本番用（公開デモ）のイメージ。リポジトリ直下をビルドの文脈にする（compose.prod.yaml から使う）。
#   target app   : PHP-FPM（コードと vendor・ビルド済みのアセットを入れる。root 以外のユーザーで動く）
#   target caddy : Caddy（public/ とビルド済みのアセット・Caddyfile を入れる。HTTPS の終端と静的ファイルの配信）
# アーキテクチャ固有のバイナリは使わない（ARM64・x86_64 のどちらでも、その環境でビルドすれば動く）。
# ビルド文脈から外すもの（環境変数ファイル・node_modules・vendor・tests・storage の中身など）は、リポジトリ直下の .dockerignore に書いてある。

# ---- アセットのビルド（Vite / Tailwind） ----
# Tailwind が Blade・PHP のクラス名を拾うため、src/ をまるごと入れてからビルドする
FROM node:24.22.1 AS assets
WORKDIR /var/www
COPY src/package.json src/package-lock.json ./
RUN npm ci
COPY src/ ./
RUN npm run build

# ---- PHP の共通の土台（拡張・ユーザー・設定） ----
FROM php:8.4-fpm AS php-base

# コンテナ内のユーザーの UID・GID。ホストの置き場所（STORAGE_PUBLIC_DIR）の所有者に合わせる（Ubuntu の最初のユーザーは 1000）
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupadd -g "${APP_GID}" appuser \
    && useradd -m -u "${APP_UID}" -g "${APP_GID}" appuser

# 拡張：pdo_mysql・gd（freetype/jpeg/webp。商品画像の縮小・WebP化）・zip・bcmath・exif・opcache。PCOV と composer は入れない
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    zlib1g-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip bcmath exif gd opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# root 以外で動かすため、www.conf の user・group（www-data）は使わない（root 以外では無視されて警告が出るため）
RUN sed -i -e 's/^user = /;user = /' -e 's/^group = /;group = /' /usr/local/etc/php-fpm.d/www.conf

# ---- vendor（本番の依存だけ。composer はここだけで、実行用のイメージには入れない） ----
FROM php-base AS vendor
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www
# アプリのクラスも classmap に入れるため、コードを入れてからインストールする。
# --no-scripts：パッケージの検出（package:discover）はビルドでは行わず、起動後の最初の artisan が行う
COPY src/ ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

# ---- 実行用の PHP-FPM ----
FROM php-base AS app
WORKDIR /var/www
COPY --from=vendor /var/www/ ./
COPY --from=assets /var/www/public/build ./public/build
COPY docker/prod/entrypoint.sh /usr/local/bin/app-entrypoint

# 書き込む場所（storage・bootstrap/cache）だけ appuser のもの。コードは root のもの（実行中に書き換えられない）。
# public/storage は storage/app/public（アップロード画像。ホストの置き場所を重ねる）へのシンボリックリンク
RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache \
    && ln -s ../storage/app/public public/storage

USER appuser
EXPOSE 9000
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---- Caddy（HTTPS・静的ファイル・PHP-FPM への中継） ----
# 公式イメージのタグはビルド前に確かめて、必要なら固定し直す
FROM caddy:2.10-alpine AS caddy
COPY docker/prod/Caddyfile /etc/caddy/Caddyfile
# PHP のコードは app 側にあり、Caddy は public/（index.php・ビルド済みのアセット）だけを持つ
COPY --from=assets /var/www/public /var/www/public
