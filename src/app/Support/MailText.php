<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * メール本文（Markdown メール）に差し込む値の整形
 *
 * Markdown メールの本文は、HTMLのエスケープに加えて Markdown としても解釈される。
 * 会員が入力した値（お届け先氏名など）が、リンク・強調・見出しなどにならないようにここで無害化する。
 */
final class MailText
{
    /**
     * 入力値を、Markdown としても HTML としても解釈されない形にする
     *
     * 半角の記号（ASCII の約物）をすべて数値文字参照にする。HTML版では記号の文字として表示され、
     * テキスト版では Markdown メールの仕組み（html_entity_decode）で元の文字に戻る。
     * 制御文字（改行など）は半角スペースにする（1行の値として扱い、本文の行を増やさせない）。
     * 戻り値は HtmlString なので、Blade の `{{ }}` で二重にエスケープされない。
     */
    public static function safe(?string $value): HtmlString
    {
        $value ??= '';

        // 不正なUTF-8のバイト列は「?」にする（記号なので、このあと文字参照になる）（メールの文字化けを防ぐ。内容は消さない）
        if (! mb_check_encoding($value, 'UTF-8')) {
            Log::warning('メールに差し込む値に不正なUTF-8のバイト列があったため、置き換えました');
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // 制御文字（0x00〜0x1F, 0x7F）はUTF-8の複数バイトの文字の中には現れないので、/u なしのバイト単位で置き換えてよい
        $oneLine = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
        $encoded = $oneLine === null ? null : preg_replace_callback(
            '/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/',
            fn (array $match): string => '&#'.ord($match[0]).';',
            $oneLine,
        );

        if ($encoded === null) {
            // 置き換えに失敗しても内容は消さず、HTMLエスケープだけして表示する
            Log::warning('メールに差し込む値の整形に失敗したため、HTMLエスケープだけで表示します');

            return new HtmlString(htmlspecialchars(self::controlToSpace($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        return new HtmlString($encoded);
    }

    /**
     * 複数行の入力値（管理者が入力したメール本文・お問い合わせの内容）を、改行を保ったまま無害化する
     *
     * 1行ずつ safe() にかけ、行の間を HTML版では <br>、テキスト版では改行にする（レイアウトが strip_tags で <br> を消す）。
     * 改行コード（CRLF・CR）は LF にそろえ、先頭と末尾の空の行（改行だけ、または半角・全角スペース・タブだけの行）は取り除く。途中の空行と空白だけの行は残す。
     * 内容のある行の行頭の半角の空白・タブ（全角スペースの字下げは Markdown に読まれないのでそのまま）は、Markdown に読まれる（4つ以上でコードブロックになり記号が消える）ため、&#160;（空白）にして保つ。
     * 先頭・末尾の空白は、メール本文の項目（MailRequest::BODY）では TrimStrings の対象外のため取り除かれずに届き、1行目の字下げも保たれる。
     * 戻り値は HtmlString なので、Blade の `{{ }}` で二重にエスケープされない。
     */
    public static function multiline(?string $value): HtmlString
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $value ?? ''));

        while ($lines !== [] && self::isBlankLine($lines[0])) {
            array_shift($lines);
        }
        while ($lines !== [] && self::isBlankLine($lines[array_key_last($lines)])) {
            array_pop($lines);
        }

        $escaped = array_map(function (string $line): string {
            preg_match('/\A[ \t]*/', $line, $indent);
            $rest = substr($line, strlen($indent[0]));

            return str_repeat('&#160;', strlen($indent[0])).self::safe($rest);
        }, $lines);

        return new HtmlString(implode("<br>\n", $escaped));
    }

    /**
     * 空の行（空・半角スペース・全角スペース・タブだけの行）か
     */
    private static function isBlankLine(string $line): bool
    {
        return preg_match('/\A[ \t\x{3000}]*\z/u', $line) === 1;
    }

    /**
     * 制御文字（0x00〜0x1F, 0x7F。改行・タブなど）を半角スペースにする（バイト単位。UTF-8の複数バイトの文字には影響しない）
     */
    private static function controlToSpace(string $value): string
    {
        return implode('', array_map(
            fn (string $byte): string => ord($byte) < 0x20 || ord($byte) === 0x7F ? ' ' : $byte,
            str_split($value),
        ));
    }

    /**
     * 金額を「¥1,850」の形にする
     */
    public static function yen(int $amount): string
    {
        return '¥'.number_format($amount);
    }

    /**
     * 日時を「2026年10月23日 14:05」の形にする
     */
    public static function dateTime(?CarbonInterface $date): string
    {
        return $date?->format('Y年n月j日 H:i') ?? '';
    }
}
