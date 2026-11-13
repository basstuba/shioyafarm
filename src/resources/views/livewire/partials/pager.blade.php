{{--
    Livewire のページ送り（マイページの一覧用）
    - $paginator：LengthAwarePaginator
    - $pageName：このコンポーネントのページ番号の名前
    - $topClass：上の余白のクラス（省略時は mt-4）
    ページが1つしかない（5件以下・0件）ときは何も出さない。ページ番号は現在のページの前後2つと、最初・最後を出す。
    見た目は、白地・薄茶の枠・角丸5pxの中に、31×31（SPは44×44）のマスを縦線で区切って横につなげた形。今のページは濃い茶色の地に白文字。
    マスが多くてSPの幅に収まらないときは、枠の中を横にスクロールする。
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $numbers = array_values(array_filter(range(1, $last), fn (int $n): bool => $n === 1 || $n === $last || abs($n - $current) <= 2));
        $cellClass = 'inline-flex size-11 shrink-0 items-center justify-center font-maru text-base md:size-[31px]';
        $buttonClass = $cellClass.' cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-text disabled:cursor-not-allowed disabled:opacity-40';
    @endphp
    <nav class="{{ $topClass ?? 'mt-4' }} flex justify-center" aria-label="ページ送り">
        <div class="inline-flex max-w-full divide-x divide-line-pager overflow-x-auto rounded-[5px] border border-line-pager bg-white">
            <button type="button" {{ \App\Support\LivewireBoot::attributes($paginator->onFirstPage()) }} wire:click="previousPage('{{ $pageName }}')" class="{{ $buttonClass }} bg-white text-primary-text enabled:hover:bg-action-hover" aria-label="前のページ">&lt;</button>
            @foreach ($numbers as $number)
                @if (! $loop->first && $number - $numbers[$loop->index - 1] > 1)
                    <span class="{{ $cellClass }} text-primary-text" aria-hidden="true">…</span>
                @endif
                <button type="button" wire:key="{{ $pageName }}-{{ $number }}" {{ \App\Support\LivewireBoot::attributes() }} wire:click="gotoPage({{ $number }}, '{{ $pageName }}')" @class([$buttonClass, $number === $current ? 'bg-primary-text text-white' : 'bg-white text-primary-text hover:bg-action-hover']) @if ($number === $current) aria-current="page" @endif aria-label="{{ $number }}ページ目">{{ $number }}</button>
            @endforeach
            <button type="button" {{ \App\Support\LivewireBoot::attributes(! $paginator->hasMorePages()) }} wire:click="nextPage('{{ $pageName }}')" class="{{ $buttonClass }} bg-white text-primary-text enabled:hover:bg-action-hover" aria-label="次のページ">&gt;</button>
        </div>
    </nav>
@endif
