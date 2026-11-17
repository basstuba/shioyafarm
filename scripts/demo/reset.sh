#!/usr/bin/env bash
# 公開デモのデータ（DB と画像）を初期状態に作り直す。サーバー（本番用の compose.prod.yaml を動かしているホスト）の cron から実行する。
#
# やること（上から順に。途中で失敗したら非0で終わる）
#   1. 安全装置の確認（compose.prod.yaml の app コンテナが動いていて、DEMO_RESET_ALLOWED=true・APP_ENV=production のときだけ進む）
#   2. メンテナンスモードにする（作り直しの間、利用者には 503 の案内を出す。最後に解除する。途中で失敗したときは解除せず、非0で終わる）
#   3. 画像：画像の保管場所から storage/app/public のホスト側の置き場所（STORAGE_PUBLIC_DIR）へ書き戻す（rsync --delete。管理画面からアップロードした画像は消える）
#   4. DB：php artisan migrate:fresh --seed --force → db:seed --class=DemoSeeder --force
#   5. キャッシュ：optimize:clear → config:cache・route:cache・view:cache
#
# 使い方（リポジトリ直下から、または任意の場所から）
#   ./scripts/demo/reset.sh                     # 画像の保管場所は docker/prod/app.env の DEMO_ASSETS_DIR
#   ./scripts/demo/reset.sh /srv/shioyafarm/assets-master   # 画像の保管場所を引数で指定
#   環境変数 DEMO_ASSETS_DIR・STORAGE_PUBLIC_DIR を先に設定しておくと、app.env の値より優先される
#
# 前提
#   - docker/prod/app.env がある（compose.prod.yaml と同じファイル。STORAGE_PUBLIC_DIR・DEMO_ASSETS_DIR・DEMO_RESET_ALLOWED=true を設定）
#   - 画像の保管場所に image/ ディレクトリがある（フリー素材はリポジトリに無いため、手元のPCなどから先に置いておく）
#   - ホストに rsync と docker compose が入っている。このスクリプトを動かすユーザーが、STORAGE_PUBLIC_DIR に書き込め、docker を使える
#   - このスクリプトを動かすユーザーの UID・GID が、app コンテナのユーザーと同じ（ビルド引数 APP_UID・APP_GID。未設定なら1000）。
#     rsync で作る画像のファイル・フォルダは、動かしたユーザーのものになる。root など別のユーザーだと、app コンテナが画像を書き込めなくなる
#     （別のユーザーで動かしたあとの --delete も失敗する）。そのため、一致しないとき（root を含む）は、何もせずに止まる
#   - cron は UID 1000 のユーザー（Ubuntu の最初のユーザー）の crontab に登録する（crontab -e）。sudo crontab（root の crontab）は使わない
#
# cron の例（毎日 日本時間の午前4時。crontab -e で登録する）
#   サーバーのタイムゾーンが Asia/Tokyo のとき：
#     0 4 * * * /home/ubuntu/shioyafarm/scripts/demo/reset.sh >> /home/ubuntu/shioyafarm-reset.log 2>&1
#   サーバーのタイムゾーンが UTC のとき（日本時間の午前4時 = 前日の 19:00 UTC）：
#     0 19 * * * /home/ubuntu/shioyafarm/scripts/demo/reset.sh >> /home/ubuntu/shioyafarm-reset.log 2>&1
#   （Ubuntu の cron は、crontab の先頭に CRON_TZ=Asia/Tokyo と書いて、上の1行目の時刻のまま使うこともできる）
#   同時に2つ動かないよう、スクリプトの中で flock をかけている（前の作り直しが終わっていなければ、何もせず非0で終わる）。
set -euo pipefail

# このスクリプトの場所（scripts/demo/）からリポジトリ直下へ
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

COMPOSE_FILE_NAME="compose.prod.yaml"
ENV_FILE="docker/prod/app.env"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$*"
}

fail() {
    log "失敗：$*" >&2
    exit 1
}

# 環境変数ファイルから、1つの値を取り出す（行頭の KEY=値。前後の引用符は外す。ファイルを source はしない）
env_value() {
    local key="$1"
    local line
    line="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line#\"}"
    line="${line%\"}"
    printf '%s' "$line"
}

# 開発用の compose.yaml など、別の Compose ファイルを使わせない（このスクリプトは compose.prod.yaml だけが対象）
unset COMPOSE_FILE COMPOSE_PROFILES COMPOSE_PROJECT_NAME COMPOSE_ENV_FILES
dcp() {
    docker compose -f "$COMPOSE_FILE_NAME" --env-file "$ENV_FILE" "$@"
}

log "デモの作り直しを始めます。"

[ -f "$COMPOSE_FILE_NAME" ] || fail "${REPO_ROOT}/${COMPOSE_FILE_NAME} がありません。"
[ -f "$ENV_FILE" ] || fail "${REPO_ROOT}/${ENV_FILE} がありません。"

# 実行するユーザーが app コンテナのユーザーと同じ UID・GID か確かめる（rsync で作るファイルの所有者がそのユーザーになるため）。
# 比べる値はビルド引数と同じ APP_UID・APP_GID（環境変数 > app.env > 1000 の順）
APP_UID_EXPECTED="${APP_UID:-$(env_value APP_UID)}"
APP_GID_EXPECTED="${APP_GID:-$(env_value APP_GID)}"
APP_UID_EXPECTED="${APP_UID_EXPECTED:-1000}"
APP_GID_EXPECTED="${APP_GID_EXPECTED:-1000}"
if [ "$(id -u)" != "$APP_UID_EXPECTED" ] || [ "$(id -g)" != "$APP_GID_EXPECTED" ]; then
    fail "実行しているユーザーの UID:GID は $(id -u):$(id -g) ですが、app コンテナのユーザーは ${APP_UID_EXPECTED}:${APP_GID_EXPECTED} です。画像の所有者がずれるため、何もせずに止まります。UID ${APP_UID_EXPECTED} のユーザーの crontab で実行してください（root・sudo では実行しない）。"
fi

command -v docker >/dev/null 2>&1 || fail "docker が見つかりません。"
command -v rsync >/dev/null 2>&1 || fail "rsync が見つかりません（sudo apt install rsync）。"

# 同時に2つ動かさない
LOCK_FILE="${TMPDIR:-/tmp}/shioyafarm-demo-reset.lock"
exec 9>"$LOCK_FILE"
flock -n 9 || fail "別の作り直しが実行中です（${LOCK_FILE}）。"

# 画像の保管場所・置き場所（保管場所は 引数 > 環境変数 DEMO_ASSETS_DIR > app.env の順。置き場所は 環境変数 STORAGE_PUBLIC_DIR > app.env の順）
ASSETS_DIR="${1:-${DEMO_ASSETS_DIR:-$(env_value DEMO_ASSETS_DIR)}}"
STORAGE_DIR="${STORAGE_PUBLIC_DIR:-$(env_value STORAGE_PUBLIC_DIR)}"

[ -n "$ASSETS_DIR" ] || fail "画像の保管場所が決まっていません（引数、または DEMO_ASSETS_DIR）。"
[ -n "$STORAGE_DIR" ] || fail "画像の置き場所が決まっていません（STORAGE_PUBLIC_DIR）。"
[ -d "$ASSETS_DIR" ] || fail "画像の保管場所 ${ASSETS_DIR} がありません。"
[ -d "$ASSETS_DIR/image" ] || fail "画像の保管場所 ${ASSETS_DIR} に image/ がありません（空のまま書き戻すと、置き場所の画像がすべて消えます）。"
[ -d "$STORAGE_DIR" ] || fail "画像の置き場所 ${STORAGE_DIR} がありません。"
ASSETS_REAL="$(cd "$ASSETS_DIR" && pwd -P)"
STORAGE_REAL="$(cd "$STORAGE_DIR" && pwd -P)"
[ "$ASSETS_REAL" != "$STORAGE_REAL" ] || fail "画像の保管場所と置き場所が同じです（${ASSETS_REAL}）。別のディレクトリにしてください。"
# 置き場所の中に保管場所がある・保管場所の中に置き場所がある場合も止める（--delete で保管場所が消えるため）
case "$ASSETS_REAL/" in "$STORAGE_REAL"/*) fail "画像の保管場所が、置き場所の中にあります。" ;; esac
case "$STORAGE_REAL/" in "$ASSETS_REAL"/*) fail "画像の置き場所が、保管場所の中にあります。" ;; esac

# 安全装置：app コンテナ（compose.prod.yaml）が動いていて、DEMO_RESET_ALLOWED=true の本番設定のときだけ進む。
# 開発用の compose.yaml には app サービスがない（php サービス）ので、開発の環境では、ここで止まる
running_services="$(dcp ps --status running --services 2>/dev/null || true)"
grep -qx app <<<"$running_services" || fail "${COMPOSE_FILE_NAME} の app コンテナが動いていません。"
dcp exec -T app sh -c 'test "${DEMO_RESET_ALLOWED:-}" = "true"' || fail "app コンテナの DEMO_RESET_ALLOWED が true ではありません。作り直しません。"
dcp exec -T app sh -c 'test "${APP_ENV:-}" = "production"' || fail "app コンテナの APP_ENV が production ではありません。作り直しません。"
log "安全装置の確認に成功しました（DEMO_RESET_ALLOWED=true・APP_ENV=production）。"

artisan() {
    dcp exec -T app php artisan "$@"
}

# 作り直しの間は利用者に 503 の案内を出し、最後に解除する。
# 途中で失敗したときは、壊れた状態を見せないよう、メンテナンスモードのままにして非0で終わる（直したあと、手動で artisan up する）
maintenance=0
on_exit() {
    local code=$?
    if [ "$code" -ne 0 ] && [ "$maintenance" -eq 1 ]; then
        log "失敗しました（終了コード ${code}）。メンテナンスモードのままです。原因を直して再実行するか、artisan up で解除してください。" >&2
    fi
}
trap on_exit EXIT
log "メンテナンスモードにします。"
artisan down --retry=60
maintenance=1

log "画像を書き戻します：${ASSETS_REAL}/ -> ${STORAGE_REAL}/（rsync --delete）"
rsync -rlt --delete --chmod=D755,F644 "${ASSETS_REAL}/" "${STORAGE_REAL}/"

log "DB を作り直します（migrate:fresh --seed）。"
artisan migrate:fresh --seed --force

log "デモ用のデータを入れます（DemoSeeder）。"
artisan db:seed --class=DemoSeeder --force

log "キャッシュを作り直します。"
artisan optimize:clear
artisan config:cache
artisan route:cache
artisan view:cache

log "メンテナンスモードを解除します。"
artisan up
maintenance=0

log "デモの作り直しが終わりました。"
