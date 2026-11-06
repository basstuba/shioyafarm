@props(['titleId', 'title', 'state' => '', 'close' => '$wire.showDetail = false', 'escape' => '$wire.showDetail = false'])

{{--
    管理画面の一覧（注文・予約・会員）の詳細モーダルの外枠（幅400px。SPは左右16pxの余白で横いっぱい）。Livewire のコンポーネントの中で使う（開閉は $wire.showDetail）。
    - titleId：見出しのid（aria-labelledby 用）、title：読み上げ用の見出し（sr-only）
    - state：外枠の x-data の中身（Alpine の状態を足すときだけ。例：{ confirming: false }）
    - close：背景・「閉じる」を押したときの式、escape：Esc を押したときの式（既定は詳細を閉じる）
    スロット：既定＝中身（項目の dl）、actions＝中身と「閉じる」の間に置くボタン、after＝パネルの外（詳細の上に重ねる確認のモーダルなど）
    下の文字だけの「閉じる」・背景・Esc で閉じる（×は付けない）。内容が長いときはモーダルの中をスクロールする。
--}}
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-data="{{ $state }}"
    x-show="$wire.showDetail"
    x-cloak
    x-on:keydown.escape.window="{{ $escape }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="absolute inset-0 bg-black/35" x-on:click="{{ $close }}" aria-hidden="true"></div>

    <div class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 pt-6 pb-3 text-ink shadow-panel lg:px-10 lg:pt-9">
        <h2 id="{{ $titleId }}" class="sr-only">{{ $title }}</h2>

        {{ $slot }}

        @isset($actions)
            {{ $actions }}
        @endisset

        {{-- 文字だけの「閉じる」 --}}
        <div class="mt-4 flex justify-center">
            <button type="button" class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="{{ $close }}">閉じる</button>
        </div>
    </div>

    @isset($after)
        {{ $after }}
    @endisset
</div>
