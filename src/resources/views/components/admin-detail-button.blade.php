@props(['label', 'event', 'id'])

{{--
    管理画面の一覧の表の「詳細」ボタン。押すと詳細モーダルの Livewire コンポーネントへブラウザのイベントを送る。注文・予約・会員の一覧で共通。
    - label：読み上げ用の名前（aria-label。例：注文12の詳細を見る）、event：Livewire のイベント名（例：open-purchase-detail）、id：開くレコードのID
    SPは枠線つきのボタン（高さ44px）、PC（lg以上）は下線つきの文字。
--}}
<button
    class="inline-flex h-11 min-w-16 cursor-pointer items-center justify-center rounded-[10px] border border-line-strong bg-white px-4 text-base text-ink hover:underline focus-visible:underline lg:border-0 lg:bg-transparent lg:px-0 lg:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
    type="button"
    aria-label="{{ $label }}"
    x-on:click="Livewire.dispatch('{{ $event }}', { id: {{ $id }} })"
>詳細</button>
