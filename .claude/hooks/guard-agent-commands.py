"""サブエージェントが実行する危険なコマンド（git の書き込み・gh・削除・DBやコンテナの破壊・npx）をブロックする PreToolUse フック

標準入力の JSON に agent_id があるとき（サブエージェント）だけ判定する。メインセッションには何もしない。
コマンドを &&・||・;・|・改行などで分け、bash -c・eval・$(...)・docker compose exec・sudo・env などの中身も調べる。
引用符の中の文字列（echo "git add" など）は、実行されるコマンドではないので調べない。
解析できないコマンドは、危険な語を含むときだけブロックする。
"""

import json
import os
import re
import sys

# 読み取りだけの git サブコマンド（これ以外はブロックする）
GIT_READ_ONLY = {
    "status", "diff", "log", "show", "blame", "grep", "ls-files",
    "rev-parse", "check-ignore", "merge-base", "describe", "cat-file",
}
# git branch で許可する引数（引数なしも許可）
GIT_BRANCH_READ_ONLY = {"--list", "--show-current", "-a", "-r", "--all", "--remotes"}
# 値を1つとる git のグローバルオプション（次の語は値）
GIT_OPTIONS_WITH_VALUE = {"-C", "-c", "--git-dir", "--work-tree", "--namespace", "--exec-path", "--super-prefix", "--config-env"}

DB_DESTRUCTIVE_ARTISAN = {"migrate:fresh", "migrate:reset", "migrate:rollback", "db:wipe"}
SHELLS = {"bash", "sh", "zsh", "dash", "ksh", "ash"}
DB_CLIENTS = {"mysql", "mariadb", "mysqladmin", "psql", "sqlite3", "mycli"}
# 先頭に付いても中のコマンドは変わらない語（シェルの予約語など）
KEYWORDS = {"if", "then", "else", "elif", "do", "while", "until", "!", "{", "}", "time", "nohup", "builtin", "exec", "setsid", "caffeinate"}

# docker exec / run で値をとるオプション（これらの次の語は値）
DOCKER_OPTIONS_WITH_VALUE = {
    "-e", "--env", "-u", "--user", "-w", "--workdir", "--index", "-v", "--volume", "-p", "--publish", "--name",
    "--entrypoint", "-l", "--label", "--cap-add", "--cap-drop", "--env-file", "--network", "--pull", "--platform",
    "--mount", "--add-host", "--hostname", "--restart", "-m", "--memory", "--gpus", "--cpus", "--env-from-file",
}
# docker / docker compose のグローバルオプションで値をとるもの
DOCKER_GLOBAL_WITH_VALUE = {
    "-H", "--host", "-c", "--context", "--config", "-l", "--log-level", "-f", "--file", "-p", "--project-name",
    "--project-directory", "--profile", "--env-file", "--ansi", "--parallel",
}

PHP_DANGER = re.compile(
    r"migrate:(?:fresh|reset|rollback)|db:wipe"
    r"|Schema\s*::\s*(?:dropAllTables|dropAllViews|dropAllTypes|dropIfExists|drop)\b"
    r"|(?:::|->)\s*truncate\s*\("
    r"|(?i:drop\s+(?:table|database|schema|view|index|column))|(?i:truncate\s+table)|\bTRUNCATE\s+[`\w]",
)
SQL_DANGER = re.compile(r"\b(?:DROP|TRUNCATE)\b", re.IGNORECASE)

MAX_DEPTH = 8


class ParseError(Exception):
    """引用符やかっこが閉じていないなど、解析できないコマンド"""


class Command:
    """1つの単純なコマンド（引用符を外した語の並びと、ヒアドキュメントの本文）"""

    def __init__(self, words, heredocs):
        self.words = words
        self.heredocs = heredocs


class Context:
    """全体の解析で見つけたものの記録（パイプや標準入力で渡される内容の検査に使う）"""

    def __init__(self, raw):
        self.raw = raw
        self.tinker_seen = False
        self.db_client_seen = False


def find_close_paren(text, i):
    """text[i] の直前に開いた ( に対応する ) の位置を返す（引用符の中は飛ばす）"""
    depth = 1
    n = len(text)
    while i < n:
        c = text[i]
        if c == "\\":
            i += 2
            continue
        if c == "'":
            j = text.find("'", i + 1)
            if j < 0:
                raise ParseError("single quote")
            i = j + 1
            continue
        if c == '"':
            i += 1
            while i < n and text[i] != '"':
                i += 2 if text[i] == "\\" else 1
            if i >= n:
                raise ParseError("double quote")
            i += 1
            continue
        if c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    raise ParseError("paren")


def read_heredoc_body(lines, start, delimiter, strip_tabs):
    """lines[start:] から、区切り語の行までのヒアドキュメント本文を取り出す。（本文, 次の行の位置）"""
    body = []
    k = start
    while k < len(lines):
        line = lines[k]
        if (line.lstrip("\t") if strip_tabs else line) == delimiter:
            return "\n".join(body), k + 1
        body.append(line)
        k += 1
    # 区切り語が見つからないときは、残りすべてを本文として扱う
    return "\n".join(body), k


def split_script(text):
    """スクリプトを単純なコマンドに分ける。（コマンドのリスト, $(...) やバッククォートの中身のリスト）"""
    commands = []
    nested = []
    words = []
    cur = []
    has_cur = False
    pending = []  # この行の < < で指定されたヒアドキュメント [(区切り語, タブを除くか)]
    line_start = 0  # この行で作ったコマンドの先頭位置
    lines = text.split("\n")
    # 文字位置から行番号をたどるため、改行ごとに処理する
    i = 0
    n = len(text)

    def end_word():
        nonlocal cur, has_cur
        if has_cur:
            words.append("".join(cur))
        cur = []
        has_cur = False

    def end_command():
        nonlocal words
        end_word()
        if words:
            commands.append(Command(words, []))
        words = []

    while i < n:
        c = text[i]
        if c == "\n":
            end_command()
            line_no = text.count("\n", 0, i) + 1  # 次の行の番号（引用符の中の改行も数える）
            if pending:
                bodies = []
                nxt = line_no
                for delimiter, strip_tabs in pending:
                    body, nxt = read_heredoc_body(lines, nxt, delimiter, strip_tabs)
                    bodies.append(body)
                for cmd in commands[line_start:]:
                    cmd.heredocs.extend(bodies)
                pending = []
                # 本文の行を飛ばして、次の行の先頭へ進む
                consumed = nxt - line_no
                for _ in range(consumed):
                    j = text.find("\n", i + 1)
                    if j < 0:
                        i = n
                        break
                    i = j
            line_start = len(commands)
            i += 1
            continue
        if c == "\\":
            if i + 1 < n and text[i + 1] == "\n":
                i += 2
                continue
            cur.append(text[i + 1] if i + 1 < n else "")
            has_cur = True
            i += 2
            continue
        if c == "'":
            j = text.find("'", i + 1)
            if j < 0:
                raise ParseError("single quote")
            cur.append(text[i + 1 : j])
            has_cur = True
            i = j + 1
            continue
        if c == '"':
            has_cur = True
            i += 1
            while True:
                if i >= n:
                    raise ParseError("double quote")
                d = text[i]
                if d == '"':
                    i += 1
                    break
                if d == "\\" and i + 1 < n:
                    cur.append(text[i + 1] if text[i + 1] in '"\\$`' else d + text[i + 1])
                    i += 2
                    continue
                if d == "$" and text.startswith("$(", i):
                    j = find_close_paren(text, i + 2)
                    nested.append(text[i + 2 : j])
                    cur.append("$(…)")
                    i = j + 1
                    continue
                if d == "`":
                    j = text.find("`", i + 1)
                    if j < 0:
                        raise ParseError("backtick")
                    nested.append(text[i + 1 : j])
                    cur.append("$(…)")
                    i = j + 1
                    continue
                cur.append(d)
                i += 1
            continue
        if c == "$" and text.startswith("$(", i):
            j = find_close_paren(text, i + 2)
            nested.append(text[i + 2 : j])
            cur.append("$(…)")
            has_cur = True
            i = j + 1
            continue
        if c in "<>" and text.startswith("(", i + 1):
            j = find_close_paren(text, i + 2)
            nested.append(text[i + 2 : j])
            cur.append("$(…)")
            has_cur = True
            i = j + 1
            continue
        if c == "`":
            j = text.find("`", i + 1)
            if j < 0:
                raise ParseError("backtick")
            nested.append(text[i + 1 : j])
            cur.append("$(…)")
            has_cur = True
            i = j + 1
            continue
        if c == "#" and not has_cur:
            j = text.find("\n", i)
            i = n if j < 0 else j
            continue
        if c == "<" and text.startswith("<<", i) and not text.startswith("<<<", i):
            # ヒアドキュメント：区切り語を読む（本文は行末で読む）
            i += 2
            strip_tabs = False
            if i < n and text[i] == "-":
                strip_tabs = True
                i += 1
            while i < n and text[i] in " \t":
                i += 1
            delim = []
            while i < n and text[i] not in " \t\n;&|()<>":
                if text[i] in "'\"":
                    q = text[i]
                    j = text.find(q, i + 1)
                    if j < 0:
                        raise ParseError("heredoc delimiter")
                    delim.append(text[i + 1 : j])
                    i = j + 1
                    continue
                if text[i] == "\\" and i + 1 < n:
                    i += 1
                delim.append(text[i])
                i += 1
            pending.append(("".join(delim), strip_tabs))
            continue
        if c in "&|;":
            # 2>&1・&>・>& の & は区切りではない
            if c == "&" and ((i > 0 and text[i - 1] in "<>") or text.startswith("&>", i)):
                cur.append(c)
                has_cur = True
                i += 1
                continue
            end_command()
            i += 1
            while i < n and text[i] in "&|;":
                i += 1
            continue
        if c in "()":
            end_command()
            i += 1
            continue
        if c in " \t":
            end_word()
            i += 1
            continue
        cur.append(c)
        has_cur = True
        i += 1
    end_command()
    if pending:
        # 本文が無いまま終わった（本文は空）
        for cmd in commands[line_start:]:
            cmd.heredocs.extend("" for _ in pending)
    return commands, nested


def basename(word):
    return os.path.basename(word.lstrip("\\"))


def fallback_scan(text):
    """解析できないコマンドを、危険な語だけで判定する。ブロックの理由か None を返す"""
    allowed = "|".join(sorted(GIT_READ_ONLY))
    for m in re.finditer(r"(?<![\w./-])git((?:\s+(?:-C\s+\S+|-c\s+\S+|--[\w-]+(?:=\S+)?|-\S+))*)\s+([A-Za-z][\w-]*)", text):
        sub = m.group(2)
        if sub not in GIT_READ_ONLY and sub != "branch":
            return f"git {sub} は書き込みを伴うため（解析できないコマンドのため語で判定）"
    if re.search(r"(?:^|[;&|(\n])\s*gh\s", text):
        return "gh（GitHub のコマンド。解析できないコマンドのため語で判定）"
    if re.search(r"(?<![\w./-])rm\s+(?:-\w*[rRf]|--recursive|--force)", text):
        return "rm の再帰・強制の削除（解析できないコマンドのため語で判定）"
    if re.search(r"migrate:(?:fresh|reset|rollback)|db:wipe", text):
        return "DBを消す artisan コマンド（解析できないコマンドのため語で判定）"
    if re.search(r"(?<![\w./-])(?:npx|bunx)(?![\w-])|\b(?:npm\s+(?:exec|x)|pnpm\s+dlx|yarn\s+dlx)\b", text):
        return "npx などのパッケージの実行（解析できないコマンドのため語で判定）"
    if re.search(r"docker(?:-compose|\s+compose)?\b[^;&|]*\bdown\b[^;&|]*(?:-v\b|--volumes)", text) or re.search(
        r"docker\s+volume\s+(?:rm|remove|prune)|docker\s+(?:\w+\s+)?prune", text
    ):
        return "docker のボリューム・コンテナの破壊（解析できないコマンドのため語で判定）"
    if re.search(r"\bfind\b[^;&|]*(?:-delete\b|-exec(?:dir)?\s+rm\b)", text):
        return "find による削除（解析できないコマンドのため語で判定）"
    if re.search(r"(?<![\w./-])(?:truncate|shred)\s|\bdd\s[^;&|]*\bof=|(?<![\w./-])mkfs", text):
        return "ファイル・ディスクを壊すコマンド（解析できないコマンドのため語で判定）"
    if re.search(r"(?<![\w./-])(?:mysql|mariadb|mysqladmin|psql|sqlite3)\b", text) and SQL_DANGER.search(text):
        return "DROP・TRUNCATE を含むDB操作（解析できないコマンドのため語で判定）"
    return None


def check_git(args):
    """git のコマンド。読み取りの許可リスト以外はブロックの理由を返す"""
    i = 0
    while i < len(args):
        a = args[i]
        if not a.startswith("-"):
            break
        if a in GIT_OPTIONS_WITH_VALUE:
            i += 2
        else:
            i += 1
    if i >= len(args):
        return None  # サブコマンドなし（git 単体・git --version など）
    sub = args[i]
    rest = args[i + 1 :]
    if sub in GIT_READ_ONLY:
        return None
    if sub == "branch":
        if all(a in GIT_BRANCH_READ_ONLY or ("--list" in rest and not a.startswith("-")) for a in rest):
            return None
        return "git branch は一覧の表示（引数なし・--list・--show-current・-a・-r）だけ許可"
    return f"git {sub} は許可していない（git は読み取り専用のサブコマンドだけ許可）"


def check_rm(args):
    for a in args:
        if a == "--":
            break
        if a.startswith("--"):
            if a in ("--recursive", "--force", "--no-preserve-root") or a.startswith("--recursive="):
                return f"rm {a}（再帰・強制の削除）"
        elif a.startswith("-") and len(a) > 1 and any(ch in "rRf" for ch in a[1:]):
            return f"rm {a}（再帰・強制の削除）"
    return None


def check_find(args, ctx, depth):
    if "-delete" in args:
        return "find -delete（削除）"
    for k, a in enumerate(args):
        if a in ("-exec", "-execdir", "-ok", "-okdir"):
            inner = []
            for w in args[k + 1 :]:
                if w in (";", "+"):
                    break
                inner.append(w)
            # find -exec rm は、オプションがなくても削除なのでブロックする
            if inner and basename(inner[0]) == "rm":
                return f"find {a} rm（削除）"
            reason = check_command(Command(inner, []), ctx, depth + 1)
            if reason:
                return f"find {a} の中：{reason}"
    return None


def skip_options(words, with_value):
    """先頭のオプション（値をとるものは値も）を飛ばした残りを返す"""
    i = 0
    while i < len(words):
        w = words[i]
        if w == "--":
            return words[i + 1 :]
        if not w.startswith("-") or w == "-":
            break
        if w in with_value:
            i += 2
        else:
            i += 1
    return words[i:]


def unwrap_docker(args):
    """docker / docker compose の exec・run の、コンテナの中で実行するコマンドを返す。なければ None"""
    rest = skip_options(args, DOCKER_GLOBAL_WITH_VALUE)
    if rest and rest[0] == "compose":
        rest = skip_options(rest[1:], DOCKER_GLOBAL_WITH_VALUE)
    if not rest or rest[0] not in ("exec", "run"):
        return None
    after = skip_options(rest[1:], DOCKER_OPTIONS_WITH_VALUE)
    return after[1:]  # 先頭はサービス名・コンテナ名・イメージ名


def check_docker(args, compose_binary=False):
    """docker のコンテナ・ボリュームを壊す操作"""
    # 値つきのグローバルオプションを飛ばして、サブコマンドを探す
    rest = skip_options(args, DOCKER_GLOBAL_WITH_VALUE)
    if not rest:
        return None
    is_compose = compose_binary or rest[0] == "compose"
    if rest[0] == "compose":
        rest = skip_options(rest[1:], DOCKER_GLOBAL_WITH_VALUE)
        if not rest:
            return None
    sub, tail = rest[0], rest[1:]
    flags = [w for w in tail if w.startswith("-")]
    has_volume_flag = any(w == "--volumes" or (not w.startswith("--") and "v" in w[1:]) for w in flags)
    if is_compose and sub in ("down", "rm") and has_volume_flag:
        return f"docker compose {sub} のボリューム削除（-v・--volumes）"
    if sub == "volume" and tail and tail[0] in ("rm", "remove", "prune"):
        return f"docker volume {tail[0]}（ボリュームの削除）"
    if sub == "prune" or (tail and tail[0] == "prune"):
        return f"docker {sub} prune（未使用のものの一括削除）"
    return None


def scan_php(text):
    m = PHP_DANGER.search(text)
    return f"DBを壊す処理（{m.group(0)}）" if m else None


def check_command(cmd, ctx, depth):
    """1つの単純なコマンドを調べる。ブロックの理由か None を返す"""
    if depth > MAX_DEPTH:
        return "コマンドの入れ子が深すぎて解析できない"
    words = list(cmd.words)
    heredocs = cmd.heredocs
    while True:
        # 先頭の VAR=x・シェルの予約語を外す
        while words and (re.match(r"^[A-Za-z_][A-Za-z0-9_]*\+?=", words[0]) or words[0] in KEYWORDS):
            words = words[1:]
        if not words:
            return None
        head = basename(words[0])
        args = words[1:]

        if head.startswith("$") or "$(…)" in head:
            # 変数やコマンド置換が先頭にあり、何を実行するかわからない
            danger = fallback_scan(ctx.raw)
            return danger
        if head == "command":
            if args and args[0] in ("-v", "-V"):
                return None
            words = args
            continue
        if head == "sudo" or head == "doas":
            words = skip_options(args, {"-u", "-g", "-h", "-p", "-C", "-D", "-R", "-T", "-U", "-r", "-t"})
            continue
        if head == "env":
            rest = skip_options(args, {"-u", "-C", "-S", "--unset", "--chdir", "--split-string"})
            words = rest
            continue
        if head == "timeout":
            rest = skip_options(args, {"-k", "--kill-after", "-s", "--signal"})
            words = rest[1:]  # 先頭は時間
            continue
        if head == "nice":
            words = skip_options(args, {"-n", "--adjustment"})
            continue
        if head == "ionice":
            words = skip_options(args, {"-c", "-n", "-p", "-P", "-u", "-t"})
            continue
        if head == "stdbuf":
            words = skip_options(args, {"-i", "-o", "-e"})
            continue
        if head == "xargs":
            words = skip_options(args, {"-I", "-n", "-P", "-L", "-d", "-E", "-s", "-a", "--max-args", "--max-procs", "--delimiter", "--arg-file", "--max-lines", "--replace"})
            continue
        if head == "watch":
            rest = skip_options(args, {"-n", "--interval", "-d"})
            return analyze(" ".join(rest), ctx, depth + 1)
        if head in SHELLS:
            return check_shell(args, heredocs, ctx, depth)
        if head == "eval":
            return analyze(" ".join(args), ctx, depth + 1)
        if head == "su":
            for k, a in enumerate(args):
                if a in ("-c", "--command") and k + 1 < len(args):
                    return analyze(args[k + 1], ctx, depth + 1)
            return None
        if head in ("docker", "docker-compose", "podman", "podman-compose"):
            reason = check_docker(args, compose_binary=head.endswith("-compose"))
            if reason:
                return reason
            inner = unwrap_docker(args)
            if inner is None:
                return None
            words = inner
            continue
        break

    # ここから、実際に実行されるコマンド（words[0]）の判定
    head = basename(words[0])
    args = words[1:]

    if head == "git":
        return check_git(args)
    if head == "gh":
        return "gh（GitHub のコマンドは、読み取りも含めてすべて許可していない）"
    if head.startswith("git-"):
        return f"{head} は git のコマンド（書き込みを伴うため許可していない）"
    if head == "rm":
        return check_rm(args)
    if head == "find":
        return check_find(args, ctx, depth)
    if head in ("truncate", "shred"):
        return f"{head}（ファイルの破壊）"
    if head == "dd" and any(a.startswith("of=") for a in args):
        return "dd of=（書き込み）"
    if head.startswith("mkfs"):
        return f"{head}（ファイルシステムの作成）"
    if head in ("npx", "bunx"):
        return f"{head}（パッケージの実行）"
    if head == "npm" and args and args[0] in ("exec", "x"):
        return f"npm {args[0]}（パッケージの実行）"
    if head in ("pnpm", "yarn") and args and args[0] == "dlx":
        return f"{head} dlx（パッケージの実行）"
    if head == "bun" and args and args[0] == "x":
        return "bun x（パッケージの実行）"

    if head in DB_CLIENTS:
        ctx.db_client_seen = True
        text = "\n".join([*args, *heredocs])
        if SQL_DANGER.search(text):
            return f"{head} への DROP・TRUNCATE"

    # artisan：DBを消すコマンド。tinker の PHP の中も調べる
    if head == "artisan" or any(basename(w) == "artisan" for w in args):
        for w in args:
            if w in DB_DESTRUCTIVE_ARTISAN:
                return f"artisan {w}（DBを消す）"
        if "tinker" in args:
            ctx.tinker_seen = True
            reason = scan_php("\n".join([*args, *heredocs]))
            if reason:
                return f"tinker：{reason}"
    if head == "php" and "-r" in args:
        reason = scan_php("\n".join(args))
        if reason:
            return f"php -r：{reason}"
    return None


def check_shell(args, heredocs, ctx, depth):
    """bash -c "..." / sh -c / ヒアドキュメントで渡したスクリプトの中身を調べる"""
    for k, a in enumerate(args):
        if a == "--":
            break
        if a.startswith("-") and not a.startswith("--") and "c" in a[1:]:
            rest = [w for w in args[k + 1 :] if not w.startswith("-")]
            if rest:
                return analyze(rest[0], ctx, depth + 1)
    for body in heredocs:
        reason = analyze(body, ctx, depth + 1)
        if reason:
            return reason
    return None


def analyze(text, ctx, depth=0):
    """スクリプト全体を調べる。ブロックの理由か None を返す"""
    if depth > MAX_DEPTH:
        return "コマンドの入れ子が深すぎて解析できない"
    try:
        commands, nested = split_script(text)
    except ParseError:
        return fallback_scan(text)
    for inner in nested:
        reason = analyze(inner, ctx, depth + 1)
        if reason:
            return reason
    for cmd in commands:
        reason = check_command(cmd, ctx, depth)
        if reason:
            return reason
    return None


def main():
    try:
        data = json.load(sys.stdin)
    except ValueError:
        sys.exit(0)  # 入力を読めないときは判定しない
    if not isinstance(data, dict) or not data.get("agent_id"):
        sys.exit(0)  # メインセッションは判定しない
    tool_input = data.get("tool_input")
    command = tool_input.get("command", "") if isinstance(tool_input, dict) else ""
    if not isinstance(command, str) or not command.strip():
        sys.exit(0)

    ctx = Context(command)
    reason = analyze(command, ctx)
    if reason is None and ctx.tinker_seen and PHP_DANGER.search(command):
        reason = "tinker に渡す内容にDBを壊す処理が含まれる"
    if reason is None and ctx.db_client_seen and SQL_DANGER.search(command):
        reason = "DBクライアントに渡す内容に DROP・TRUNCATE が含まれる"
    if reason is None:
        sys.exit(0)
    print(
        f"サブエージェントには許可していない操作です：{reason}。"
        "必要な場合は、作業を止めて、メインセッションに報告してください（実行はメインセッションが判断します）。",
        file=sys.stderr,
    )
    sys.exit(2)


main()
