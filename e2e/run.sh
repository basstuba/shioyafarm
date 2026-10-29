#!/usr/bin/env bash
# E2Eテストを実行する（リポジトリ直下で ./e2e/run.sh）
#   ./e2e/run.sh                 普段の実行（@stripe 以外）
#   ./e2e/run.sh stripe          クレジットカード（Stripe Checkout）の項目だけ
#   ./e2e/run.sh tests/auth.spec.ts -g E-1   playwright test にそのまま渡す
# 実行のたびに専用DB（sf_db_e2e）を作り直す。開発用のDB・:80 のアプリには触れない
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose --profile e2e up -d php-e2e nginx-e2e
# E2Eではビルド済みのCSS/JSを使う（AppServiceProvider で public/hot を見ないようにしている）
docker compose exec -T node npm run build
docker compose exec -T php-e2e php artisan migrate:fresh --seed --force

if [ ! -d e2e/node_modules ]; then
    docker compose --profile e2e run --rm --no-deps playwright npm ci
fi

if [ "${1:-}" = "stripe" ]; then
    shift
    docker compose --profile e2e run --rm playwright npm run test:stripe -- "$@"
else
    docker compose --profile e2e run --rm playwright npm test -- "$@"
fi
