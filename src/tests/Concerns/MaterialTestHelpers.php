<?php

namespace Tests\Concerns;

/**
 * 管理画面の資材一覧のテストで使う補助
 */
trait MaterialTestHelpers
{
    /**
     * 一覧のHTMLから、モーダルの初期の状態（x-data に入れた MaterialFormState の JSON）を取り出す
     *
     * @return array{formOpen: bool, mode: string, id: string, name: string, stock: string, location: string, threshold: string, showErrors: bool, invalid: array<string, bool>}
     */
    private function formState(string $html): array
    {
        $this->assertSame(1, preg_match("/\.\.\.JSON\.parse\('(.*?)'\)/s", $html, $matches), 'x-data に初期の状態がない');

        // Js::from は JSON を JS の文字列にして出す（" などを \uXXXX に、日本語は \\uXXXX にする）。JS の文字列として読み直すと JSON になる
        $json = preg_replace_callback(
            '/\\\\(?:u([0-9a-fA-F]{4})|(.))/su',
            fn (array $escape): string => $escape[1] !== '' ? (string) mb_chr((int) hexdec($escape[1])) : $escape[2],
            $matches[1],
        );

        return json_decode((string) $json, true, flags: JSON_THROW_ON_ERROR);
    }
}
