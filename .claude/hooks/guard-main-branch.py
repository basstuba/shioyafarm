"""main ブランチ上で src/ 配下を編集しようとしたらブロックする PreToolUse フック"""

import json
import os
import subprocess
import sys

PROTECTED_BRANCHES = {"main"}
PROTECTED_DIR = "src"

data = json.load(sys.stdin)
tool_input = data.get("tool_input", {})
file_path = tool_input.get("file_path") or tool_input.get("notebook_path")
if not file_path:
    sys.exit(0)

project_dir = os.environ.get("CLAUDE_PROJECT_DIR") or data.get("cwd") or os.getcwd()
abs_path = os.path.realpath(os.path.join(data.get("cwd") or project_dir, file_path))
rel_path = os.path.relpath(abs_path, os.path.realpath(project_dir))

# src/ 配下以外（docs、CLAUDE.md、.claude など）は対象外
if rel_path != PROTECTED_DIR and not rel_path.startswith(PROTECTED_DIR + os.sep):
    sys.exit(0)

branch = subprocess.run(
    ["git", "-C", project_dir, "branch", "--show-current"],
    capture_output=True,
    text=True,
).stdout.strip()

if branch in PROTECTED_BRANCHES:
    print(
        f"{branch} ブランチ上では {PROTECTED_DIR}/ を編集できません。"
        "作業ブランチ（例：feat/xxx）を切ってから編集してください。",
        file=sys.stderr,
    )
    sys.exit(2)
