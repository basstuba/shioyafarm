#!/usr/bin/env bash
# main にマージする前の検証（リポジトリ直下で ./scripts/verify.sh）
# GitHub Actions（.github/workflows/ci.yml）と同じ項目を、手元の php・node コンテナで実行する。
# すべて通ったら、そのコミットを .git/verified-commits に記録する。
# main 上の git merge は、マージするブランチの先頭コミットがここに記録されていないと hook で止まる。
set -euo pipefail
cd "$(dirname "$0")/.."

# 未コミットの変更があると、記録するコミットと検証した中身がずれるため止める
if [ -n "$(git status --porcelain)" ]; then
    echo "未コミットの変更があります。コミットしてから実行してください。" >&2
    git status --short >&2
    exit 1
fi

commit=$(git rev-parse HEAD)
branch=$(git branch --show-current)
echo "検証するコミット：${branch} ${commit:0:7}"

step() {
    echo
    echo "==> $1"
}

step "コードスタイル（Pint）"
docker compose exec -T php ./vendor/bin/pint --test

step "静的解析（Larastan）"
docker compose exec -T php ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

step "テストとカバレッジ（80%未満で失敗）"
docker compose exec -T php php artisan test --coverage --min=80

step "依存の脆弱性（composer audit）"
docker compose exec -T php composer audit

# node コンテナでは Vite の開発サーバーが動いているため、npm ci で node_modules を入れ直すことはしない
step "ビルド"
docker compose exec -T node npm run build

step "依存の脆弱性（npm audit）"
docker compose exec -T node npm audit

echo "$commit" >> "$(git rev-parse --git-path verified-commits)"
echo
echo "すべて通りました。${branch} ${commit:0:7} を検証済みとして記録しました。"
