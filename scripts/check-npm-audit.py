#!/usr/bin/env python3
"""npm audit --json の出力（標準入力）を読み、例外にない脆弱性があれば失敗させる。

使い方（src/ で実行する。npm audit は脆弱性があると非0で終わるので || true で受ける）：
    (npm audit --json || true) | python3 ../scripts/check-npm-audit.py

- 例外にした脆弱性（GHSA ID）は scripts/npm-audit-exceptions.json に書く。
- 例外にない脆弱性が1つでもあれば終了コード1（重大度が低いものも含む。今の npm audit と同じ）。
- npm audit 自体のエラー（ネットワークなど）や、読み取れない出力も終了コード1。
- 例外にした脆弱性がもう出ていないときは、例外を外してよいという警告を出す（失敗にはしない）。
"""

import json
import os
import re
import sys

EXCEPTIONS_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "npm-audit-exceptions.json")


def fail(message):
    print(f"npm audit の判定：失敗。{message}", file=sys.stderr)
    sys.exit(1)


def warn(message):
    # GitHub Actions ではログの上部に警告として出る
    prefix = "::warning::" if os.environ.get("GITHUB_ACTIONS") else ""
    print(f"{prefix}警告：{message}", file=sys.stderr)


def load_exceptions():
    try:
        with open(EXCEPTIONS_FILE, encoding="utf-8") as f:
            data = json.load(f)
        return {e["id"]: e for e in data["exceptions"]}
    except (OSError, ValueError, KeyError, TypeError) as e:
        fail(f"例外の一覧（{EXCEPTIONS_FILE}）を読めません：{e}")


def advisory_id(via):
    """via のオブジェクトから GHSA ID を取り出す。取れないときは url（それもなければ title）を返す。"""
    url = via.get("url") or ""
    m = re.search(r"GHSA-[0-9a-z]{4}-[0-9a-z]{4}-[0-9a-z]{4}", url, re.IGNORECASE)
    if m:
        return m.group(0).lower()
    return url or via.get("title") or "不明な advisory"


def main():
    raw = sys.stdin.read()
    try:
        report = json.loads(raw)
    except ValueError:
        fail("npm audit の出力を JSON として読めません（空の出力を含む）。出力：" + raw[:300])

    if not isinstance(report, dict) or "error" in report:
        detail = report.get("error") if isinstance(report, dict) else report
        fail(f"npm audit がエラーを返しました：{json.dumps(detail, ensure_ascii=False)[:300]}")
    if not isinstance(report.get("vulnerabilities"), dict):
        fail("npm audit の出力に vulnerabilities がありません。")

    exceptions = load_exceptions()
    allowed = {k.lower(): v for k, v in exceptions.items()}

    # パッケージごとに、via のうちオブジェクトのもの（advisory 本体）を集める。
    # 文字列の via は、ほかのパッケージ経由という意味で、その元の advisory で判定される。
    found = {}  # advisory の ID -> {"title", "severity", "packages"}
    for name, vuln in report["vulnerabilities"].items():
        for via in vuln.get("via", []):
            if not isinstance(via, dict):
                continue
            entry = found.setdefault(
                advisory_id(via),
                {"title": via.get("title", ""), "severity": via.get("severity", ""), "packages": set()},
            )
            entry["packages"].add(name)

    unexpected = {k: v for k, v in found.items() if k not in allowed}
    # 脆弱性として報告されているのに advisory が1件も見つからない場合は、判定できないので失敗にする
    if report["vulnerabilities"] and not found:
        fail("vulnerabilities はあるのに advisory を特定できません。npm audit の出力形式が変わった可能性があります。")

    for key, entry in found.items():
        if key in allowed:
            print(f"例外として許可：{key}（{entry['severity']}）{entry['title']}")

    for key, e in allowed.items():
        if key not in found:
            warn(f"例外 {e['id']} の脆弱性はもう出ていません。{e.get('remove_when', '')}"
                 "scripts/npm-audit-exceptions.json から例外を外してください。")

    if unexpected:
        for key, entry in unexpected.items():
            print(
                f"例外にない脆弱性：{key}（{entry['severity']}）{entry['title']} "
                f"[{', '.join(sorted(entry['packages']))}]",
                file=sys.stderr,
            )
        fail(f"例外にない脆弱性が {len(unexpected)} 件あります。")

    print("npm audit の判定：成功（例外にない脆弱性はありません）")


if __name__ == "__main__":
    main()
