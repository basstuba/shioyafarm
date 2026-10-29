#!/usr/bin/env bash
# E2Eテストを実行する（リポジトリ直下で ./e2e/run.sh）
#   ./e2e/run.sh                 普段の実行（@stripe 以外）
#   ./e2e/run.sh stripe          クレジットカード（Stripe Checkout）の項目だけ
#   ./e2e/run.sh tests/auth.spec.ts -g E-1   playwright test にそのまま渡す
# 実行のたびに専用DB（sf_db_e2e）を作り直す。開発用のDB・:80 のアプリには触れない
set -euo pipefail
cd "$(dirname "$0")/.."

# mysql・mail も一緒に起動する（開発用のコンテナが止まっていても動くように）
docker compose --profile e2e up -d php-e2e nginx-e2e
# E2Eではビルド済みのCSS/JSを使う（AppServiceProvider で public/hot を見ないようにしている）
docker compose run --rm --no-deps node npm run build

# 接続先が sf_db_e2e でなければ止める（設定キャッシュなどで開発用DBを指していたら、作り直すと全データが消えるため）
database=$(docker compose exec -T php-e2e php artisan tinker --execute 'echo config("database.connections.mysql.database");')
if [ "$database" != "sf_db_e2e" ]; then
    echo "E2E用アプリの接続先DBが sf_db_e2e ではありません（${database}）。bootstrap/cache/config.php が残っていないか確認してください。" >&2
    exit 1
fi
docker compose exec -T php-e2e php artisan migrate:fresh --seed --force

# 依存が未インストール、または package-lock.json が更新されたときだけ入れ直す
if [ ! -f e2e/node_modules/.package-lock.json ] || [ e2e/package-lock.json -nt e2e/node_modules/.package-lock.json ]; then
    docker compose --profile e2e run --rm --no-deps playwright npm ci
fi

if [ "${1:-}" = "stripe" ]; then
    shift
    docker compose --profile e2e run --rm playwright npm run test:stripe -- "$@"
else
    docker compose --profile e2e run --rm playwright npm test -- "$@"
fi
