@props(['action', 'label', 'resetHref'])

{{--
    管理画面の一覧（注文・予約・会員）の検索フォーム（GET）。検索欄の項目はスロットに <x-admin-search-field> で置く。
    末尾に「検索」「リセット」のボタン（SPは2列、PCは項目と同じ行）を出す。
    フォームの下に、操作が成功した案内（flash の list_status。緑の文字・中央寄せ）と、入力の誤りなど $errors のメッセージの一覧（赤字・中央寄せ）を出す。
    - action：検索のURL、label：フォームの aria-label、resetHref：リセット（全件の一覧）のURL
--}}
<form class="mt-6 flex w-full flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-center" action="{{ $action }}" method="GET" role="search" aria-label="{{ $label }}">
    {{ $slot }}
    <div class="grid grid-cols-2 gap-4 lg:contents">
        <x-admin-search-button variant="search">検索</x-admin-search-button>
        <x-admin-search-button variant="reset" :href="$resetHref">リセット</x-admin-search-button>
    </div>
</form>

{{-- 一覧からの操作が成功した案内（flash の list_status。例：会員を削除しました。）。背景・枠なしの緑の文字。layout の status（x-admin-status）とは別に、検索欄の下に出す --}}
@if (session('list_status'))
    <p class="mt-3 w-full text-center text-base text-accent-hover" role="status">{{ session('list_status') }}</p>
@endif

{{-- 検索条件の誤り（注文日が日付でないなど）・削除できなかった理由は、検索欄の近くに出す --}}
@if ($errors->any())
    <ul class="mt-3 w-full text-center text-base text-danger" role="alert">
        @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
