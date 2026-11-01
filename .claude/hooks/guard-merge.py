"""main 上の git merge で、マージするブランチの先頭コミットが scripts/verify.sh で検証済みでなければブロックする PreToolUse フック"""

import json
import os
import re
import shlex
import subprocess
import sys

PROTECTED_BRANCHES = {"main"}
# 値を1つとるオプション（この次の語はマージ対象のブランチではない）
OPTIONS_WITH_VALUE = {"-m", "-F", "-s", "-X", "--strategy", "--strategy-option", "--file", "--cleanup"}
# マージの続きや取り消しで、新しいブランチを取り込まないもの
SKIP_OPTIONS = {"--abort", "--continue", "--quit"}

data = json.load(sys.stdin)
command = data.get("tool_input", {}).get("command", "")
if "git" not in command or "merge" not in command:
    sys.exit(0)

project_dir = os.environ.get("CLAUDE_PROJECT_DIR") or data.get("cwd") or os.getcwd()


def git(*args):
    return subprocess.run(["git", "-C", project_dir, *args], capture_output=True, text=True)


def merge_targets(command):
    """コマンド中の git merge ごとに、マージ対象のブランチ名を返す"""
    targets = []
    for part in re.split(r"&&|\|\||;|\|", command):
        try:
            words = shlex.split(part)
        except ValueError:
            continue
        if len(words) < 2 or words[0] != "git":
            continue
        # git -C <dir> merge のような書き方にも対応する
        i = 1
        while i < len(words) and words[i].startswith("-"):
            i += 2 if words[i] in {"-C", "-c"} else 1
        if i >= len(words) or words[i] != "merge":
            continue
        args = words[i + 1 :]
        if any(a in SKIP_OPTIONS for a in args):
            continue
        j = 0
        while j < len(args):
            arg = args[j]
            if arg in OPTIONS_WITH_VALUE:
                j += 2
                continue
            if not arg.startswith("-"):
                targets.append(arg)
            j += 1
    return targets


targets = merge_targets(command)
if not targets:
    sys.exit(0)

branch = git("branch", "--show-current").stdout.strip()
if branch not in PROTECTED_BRANCHES:
    sys.exit(0)

verified_path = git("rev-parse", "--git-path", "verified-commits").stdout.strip()
if not os.path.isabs(verified_path):
    verified_path = os.path.join(project_dir, verified_path)
try:
    with open(verified_path) as f:
        verified = {line.strip() for line in f if line.strip()}
except FileNotFoundError:
    verified = set()

for target in targets:
    commit = git("rev-parse", "--verify", "--quiet", target + "^{commit}").stdout.strip()
    if not commit:
        # ブランチ名が解決できない場合は git 自身のエラーに任せる
        continue
    if commit not in verified:
        print(
            f"{target}（{commit[:7]}）はマージ前の検証がまだです。"
            f"{target} に切り替えて ./scripts/verify.sh を実行し、すべて通ってからマージしてください。",
            file=sys.stderr,
        )
        sys.exit(2)
