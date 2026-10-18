"""既存のマイグレーションファイルの書き換えをブロックする PreToolUse フック

次のどちらかに当てはまるファイルへの Edit / Write（上書き）をブロックする。新しいファイルの作成と、未適用のファイルの編集は許可する。
- main ブランチにコミット済みのマイグレーション
- DBに適用済み（migrate:status が Ran）のマイグレーション
適用状況を確認できない場合（コンテナが止まっているなど）は、安全のためブロックする。
"""

import json
import os
import re
import subprocess
import sys

BASE_BRANCH = "main"
MIGRATIONS_DIR = os.path.join("src", "database", "migrations")


def block(message):
    print(message, file=sys.stderr)
    sys.exit(2)


data = json.load(sys.stdin)
tool_input = data.get("tool_input", {})
file_path = tool_input.get("file_path")
if not file_path:
    sys.exit(0)

project_dir = os.environ.get("CLAUDE_PROJECT_DIR") or data.get("cwd") or os.getcwd()
abs_path = os.path.realpath(os.path.join(data.get("cwd") or project_dir, file_path))
rel_path = os.path.relpath(abs_path, os.path.realpath(project_dir))

# マイグレーション以外は対象外
if os.path.dirname(rel_path) != MIGRATIONS_DIR or not rel_path.endswith(".php"):
    sys.exit(0)

# まだ存在しないファイルの作成は許可する
if not os.path.exists(abs_path):
    sys.exit(0)

name = os.path.basename(rel_path)
migration = name[: -len(".php")]
fix_hint = "修正用のマイグレーション（例：add_xxx_to_yyy_table）を新しく作成してください。"

in_base_branch = (
    subprocess.run(
        ["git", "-C", project_dir, "cat-file", "-e", f"{BASE_BRANCH}:{rel_path}"],
        capture_output=True,
    ).returncode
    == 0
)
if in_base_branch:
    block(f"{name} は {BASE_BRANCH} にコミット済みのマイグレーションのため書き換えできません。{fix_hint}")

# DBへの適用状況を確認する
try:
    result = subprocess.run(
        ["docker", "compose", "exec", "-T", "php", "php", "artisan", "migrate:status"],
        cwd=project_dir,
        capture_output=True,
        text=True,
        timeout=30,
    )
except (OSError, subprocess.TimeoutExpired):
    result = None

output = (result.stdout + result.stderr) if result else ""

# migrations テーブルがまだない＝何も適用されていない
if "Migration table not found" in output:
    sys.exit(0)

if result is None or result.returncode != 0:
    block(
        f"{name} のDBへの適用状況を確認できなかったため、書き換えをブロックしました。"
        "`docker compose up -d` でコンテナを起動してから、もう一度実行してください。"
    )

if re.search(rf"^\s*{re.escape(migration)}\s.*\bRan\b", output, re.MULTILINE):
    block(f"{name} はDBに適用済みのマイグレーションのため書き換えできません。{fix_hint}")
