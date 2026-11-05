{{--
    管理画面のページ送り（リンクの通常のページ送り。検索条件は withQueryString で保たれる）
    - $paginator：LengthAwarePaginator
    ページが1つしかない（10件以下・0件）ときは何も出さない。ページ番号は現在のページの前後2つと、最初・最後を出す。
    見た目は、公開側のマイページのページ送り（livewire/partials/pager）と同じ形で、色は灰色。
    31×31（SPは44×44）のマスを縦線で区切って横につなげ、今のページは灰色の地に白文字。マスが多くてSPの幅に収まらないときは、枠の中を横にスクロールする。
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $numbers = array_values(array_filter(range(1, $last), fn (int $n): bool => $n === 1 || $n === $last || abs($n - $current) <= 2));
        $cellClass = 'inline-flex size-11 shrink-0 items-center justify-center font-maru text-base text-admin-gray lg:size-[31px]';
        $linkClass = $cellClass.' bg-white hover:bg-admin-bar-end focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ink';
    @endphp
    <nav class="mt-6 flex w-full justify-center" aria-label="ページ送り">
        <div class="inline-flex max-w-full divide-x divide-line-strong overflow-x-auto rounded-[5px] border border-line-strong bg-white">
            @if ($paginator->onFirstPage())
                <span class="{{ $cellClass }} opacity-40" aria-hidden="true">&lt;</span>
            @else
                <a class="{{ $linkClass }}" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="前のページ">&lt;</a>
            @endif
            @foreach ($numbers as $number)
                @if (! $loop->first && $number - $numbers[$loop->index - 1] > 1)
                    <span class="{{ $cellClass }}" aria-hidden="true">…</span>
                @endif
                @if ($number === $current)
                    <span class="{{ $cellClass }} bg-admin-gray text-white" aria-current="page" aria-label="{{ $number }}ページ目">{{ $number }}</span>
                @else
                    <a class="{{ $linkClass }}" href="{{ $paginator->url($number) }}" aria-label="{{ $number }}ページ目">{{ $number }}</a>
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a class="{{ $linkClass }}" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="次のページ">&gt;</a>
            @else
                <span class="{{ $cellClass }} opacity-40" aria-hidden="true">&gt;</span>
            @endif
        </div>
    </nav>
@endif
