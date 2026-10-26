{{--
    Livewire のページ送り（マイページの一覧用。見た目は仮置き）
    - $paginator：LengthAwarePaginator
    - $pageName：このコンポーネントのページ番号の名前
    ページが1つしかない（5件以下・0件）ときは何も出さない。ページ番号は現在のページの前後2つと、最初・最後を出す。
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $numbers = array_values(array_filter(range(1, $last), fn (int $n): bool => $n === 1 || $n === $last || abs($n - $current) <= 2));
        $buttonClass = 'inline-flex size-11 cursor-pointer items-center justify-center rounded-full border border-primary-dark text-base text-ink transition-colors hover:bg-action-hover focus-visible:bg-action-hover focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white';
    @endphp
    <nav class="mt-4 flex flex-wrap items-center justify-center gap-1.5 md:gap-2" aria-label="ページ送り">
        <button type="button" wire:click="previousPage('{{ $pageName }}')" @disabled($paginator->onFirstPage()) class="{{ $buttonClass }} bg-white" aria-label="前のページ">＜</button>
        @foreach ($numbers as $number)
            @if (! $loop->first && $number - $numbers[$loop->index - 1] > 1)
                <span class="px-1" aria-hidden="true">…</span>
            @endif
            <button type="button" wire:key="{{ $pageName }}-{{ $number }}" wire:click="gotoPage({{ $number }}, '{{ $pageName }}')" @class([$buttonClass, $number === $current ? 'bg-action-hover font-semibold' : 'bg-white']) @if ($number === $current) aria-current="page" @endif aria-label="{{ $number }}ページ目">{{ $number }}</button>
        @endforeach
        <button type="button" wire:click="nextPage('{{ $pageName }}')" @disabled(! $paginator->hasMorePages()) class="{{ $buttonClass }} bg-white" aria-label="次のページ">＞</button>
    </nav>
@endif
