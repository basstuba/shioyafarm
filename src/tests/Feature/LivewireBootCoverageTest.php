<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * wire:click・wire:change・wire:model（.live の有無を問わない）を持つ要素に、Livewire が起動する前に押せなくする属性（LivewireBoot）の付け忘れがないことのテスト
 */
class LivewireBootCoverageTest extends TestCase
{
    /**
     * LivewireBoot を付けなくてよい要素（ビューのパス => [要素の開始タグに含まれる文字列 => 理由]）
     *
     * 新しく足すときは、起動前に押されても困らない理由を書く。
     *
     * @var array<string, array<string, string>>
     */
    private const array EXCLUDED = [
        'livewire/partials/address-modal.blade.php' => [
            'wire:click="addAddress"' => 'モーダルの中のボタン。モーダルは最初の表示では開いておらず、開くボタン（openAddressModal）が起動後でないと押せないため、起動前に押されることがない',
            'wire:model="delivery_prefecture"' => 'モーダルの中の都道府県の選択欄。モーダルは最初の表示では開いておらず、開くボタン（openAddressModal）が起動後でないと押せないため、起動前に操作されることがない',
            'wire:model="delivery_city"' => 'モーダルの中の住所の入力欄。モーダルは最初の表示では開いておらず、開くボタン（openAddressModal）が起動後でないと押せないため、起動前に操作されることがない',
        ],
    ];

    /**
     * resources/views 以下の Blade（相対パス => 中身）。Blade のコメントは除く
     *
     * @return array<string, string>
     */
    private function bladeFiles(): array
    {
        $root = resource_path('views');
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $files[$relative] = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($file->getPathname()));
        }
        ksort($files);

        return $files;
    }

    /**
     * 開始タグ（<button ... > や <x-outline-button ... >）を、先頭から順に取り出す
     *
     * 属性の値の中や {{ }} の中の > （->、=> など）、Blade のディレクティブの引数（@class([...])、@disabled($a > $b) など。入れ子の括弧も）の中の > では終わらせない。
     *
     * @return list<string>
     */
    private function openingTags(string $source): array
    {
        $tags = [];
        $length = strlen($source);
        preg_match_all('/<[a-z][\w:.-]*/i', $source, $starts, PREG_OFFSET_CAPTURE);

        foreach ($starts[0] as [, $offset]) {
            $i = $offset + 1;
            while ($i < $length) {
                $two = substr($source, $i, 2);
                if ($two === '{{' || $two === '{!') {
                    $close = strpos($source, $two === '{{' ? '}}' : '!!}', $i);
                    $i = $close === false ? $length : $close + 2;

                    continue;
                }
                if ($source[$i] === '@' && preg_match('/\G@\w+\s*\(/', $source, $directive, 0, $i) === 1) {
                    $i = $this->skipParentheses($source, $i + strlen($directive[0]) - 1);

                    continue;
                }
                $char = $source[$i];
                if ($char === '"' || $char === "'") {
                    // 属性の値の中（{{ }} を含む）を飛ばす
                    $i++;
                    while ($i < $length && $source[$i] !== $char) {
                        if (substr($source, $i, 2) === '{{') {
                            $close = strpos($source, '}}', $i);
                            $i = $close === false ? $length : $close + 2;

                            continue;
                        }
                        $i++;
                    }
                    $i++;

                    continue;
                }
                if ($char === '>') {
                    break;
                }
                $i++;
            }
            $tags[] = substr($source, $offset, $i - $offset + 1);
        }

        return $tags;
    }

    /**
     * 開いた括弧（$open の位置の「(」）から、対応する閉じ括弧の次の位置を返す。入れ子の括弧と引用符の中は数えない
     */
    private function skipParentheses(string $source, int $open): int
    {
        $length = strlen($source);
        $depth = 0;
        $i = $open;
        while ($i < $length) {
            $char = $source[$i];
            if ($char === '"' || $char === "'") {
                $i++;
                while ($i < $length && $source[$i] !== $char) {
                    $i += $source[$i] === '\\' ? 2 : 1;
                }
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
            $i++;
        }

        return $length;
    }

    /**
     * 開始タグが LivewireBoot（直接の呼び出し、または x-outline-button の livewire 属性）を使っているか
     */
    private function usesLivewireBoot(string $tag): bool
    {
        return str_contains($tag, 'LivewireBoot::attributes(')
            || str_contains($tag, 'LivewireBoot::inputAttributes(')
            || str_contains($tag, 'LivewireBoot::lockAttributes(')
            || (str_starts_with($tag, '<x-outline-button') && preg_match('/\slivewire(?=[\s>\/=])/', $tag) === 1)
            || (str_starts_with($tag, '<x-select') && preg_match('/\slock(?=[\s>\/=])/', $tag) === 1);
    }

    /**
     * 起動前に操作されると困る wire: の指定（wire:click・wire:change・wire:model。.live や .blur などの修飾子は問わない）を持つ開始タグか
     *
     * wire:model.live は、変えると更新リクエストを送って金額などを計算し直す。起動前に変えられると、起動時に選択が戻るおそれがある。
     * .live のない wire:model も、Livewire は起動時にプロパティの値を欄へ書き戻すため、起動前に入力した文字が元に戻るおそれがある。
     */
    private function hasWireAction(string $tag): bool
    {
        if (preg_match('/\swire:(click|change)(?=[\s=.])/', $tag) === 1) {
            return true;
        }

        return preg_match('/\swire:model(?:\.[\w-]+)*(?=[\s=])/', $tag) === 1;
    }

    /**
     * wire:click・wire:change・wire:model を持つ要素には、LivewireBoot が付いている（除外の一覧にあるものを除く）
     */
    #[Test]
    public function wire_clickとwire_changeとwire_modelの要素には_livewire_bootが付いている(): void
    {
        $missing = [];
        $found = 0;

        foreach ($this->bladeFiles() as $path => $source) {
            foreach ($this->openingTags($source) as $tag) {
                if (! $this->hasWireAction($tag)) {
                    continue;
                }
                $found++;
                if ($this->usesLivewireBoot($tag)) {
                    continue;
                }
                $excluded = false;
                foreach (self::EXCLUDED[$path] ?? [] as $needle => $reason) {
                    $excluded = $excluded || str_contains($tag, $needle);
                }
                if (! $excluded) {
                    $missing[] = $path.' : '.preg_replace('/\s+/', ' ', $tag);
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'wire:click・wire:change・wire:model の要素が1つも見つからない（探し方が壊れている）');
        $this->assertSame([], $missing, "LivewireBoot の付け忘れ。App\\Support\\LivewireBoot を付けるか、起動前に押されても困らない理由を EXCLUDED に書く。\n".implode("\n", $missing));
    }

    /**
     * 除外の一覧は、今もある要素だけを指している（要素を消したり変えたりしたあとの残りがない）
     */
    #[Test]
    public function 除外の一覧は存在する要素だけを指している(): void
    {
        $files = $this->bladeFiles();

        foreach (self::EXCLUDED as $path => $entries) {
            $this->assertArrayHasKey($path, $files, $path.' がない');
            foreach ($entries as $needle => $reason) {
                $hit = array_filter(
                    $this->openingTags($files[$path]),
                    fn (string $tag): bool => str_contains($tag, $needle) && ! $this->usesLivewireBoot($tag),
                );
                $this->assertNotEmpty($hit, $path.' に '.$needle.' の要素がない（除外が古い、または LivewireBoot を付けたので除外は不要）');
                $this->assertNotSame('', $reason);
            }
        }
    }

    /**
     * 付け忘れの探し方が働いている：LivewireBoot のない wire:click・wire:model（.live の有無を問わない）の要素は見つかり、複数行・->を含む呼び出しは付いているとみなす
     */
    #[Test]
    public function 探し方は付け忘れを見つけ付いているものは見逃さない(): void
    {
        $source = <<<'BLADE'
            <button type="button" wire:click="a">A</button>
            <button type="button" {{ \App\Support\LivewireBoot::attributes($p->onFirstPage()) }} wire:click="b('{{ $n }}')">B</button>
            <input
                {{ \App\Support\LivewireBoot::inputAttributes() }}
                wire:change="c($event.target.value)"
            >
            <x-outline-button type="button" livewire wire:click="d">D</x-outline-button>
            <x-outline-button type="button" wire:click="e">E</x-outline-button>
            <input type="radio" wire:model.live="f">
            <input type="radio" {{ \App\Support\LivewireBoot::lockAttributes() }} wire:model.live="g">
            <x-select wire:model.live="h" lock></x-select>
            <x-select wire:model.live="i"></x-select>
            <input wire:model.live.debounce.300ms="j">
            <input wire:model="k">
            <input {{ \App\Support\LivewireBoot::inputAttributes() }} wire:model="l">
            <input wire:model.blur="m">
            BLADE;

        $tags = array_values(array_filter($this->openingTags($source), fn (string $tag): bool => $this->hasWireAction($tag)));

        $this->assertCount(13, $tags);
        $this->assertSame([false, true, true, true, false, false, true, true, false, false, false, true, false], array_map(fn (string $tag): bool => $this->usesLivewireBoot($tag), $tags));
    }

    /**
     * Blade のディレクティブの引数（入れ子の括弧・引用符を含む）の中の > で開始タグを終わらせず、そのあとの wire:click を見落とさない
     */
    #[Test]
    public function ディレクティブの引数の中の大なりでは開始タグを終わらせない(): void
    {
        $source = <<<'BLADE'
            <button type="button" @class(['a' => $on, 'b' => ($x > 1), 'c' => ">"]) wire:click="a">A</button>
            <button type="button" @disabled($a > $b) wire:click="b">B</button>
            <button type="button" @if ($a > $b) aria-current="page" @endif wire:click="c">C</button>
            <button type="button" wire:click="d">D</button>
            BLADE;

        $tags = array_values(array_filter($this->openingTags($source), fn (string $tag): bool => $this->hasWireAction($tag)));

        $this->assertCount(4, $tags);
        $this->assertStringContainsString('wire:click="a"', $tags[0]);
        $this->assertStringContainsString('wire:click="b"', $tags[1]);
        $this->assertStringContainsString('wire:click="c"', $tags[2]);
    }
}
