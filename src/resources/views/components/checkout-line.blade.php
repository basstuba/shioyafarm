@props([
    'item',
    'amount',
    'inputId',
    'name',
    'value',
    'min',
    'max',
    'change',
    'error' => false,
    'tag' => 'div',
])

{{--
    購入ページ・予約ページで共通の「商品の行」（画像・商品名・単価・数量・金額）。
    SPは2段（上：画像と商品名・単価／下：数量・金額）。PCは1行に並べる（md:contents で段の枠を無くす）。
    列の幅はFigmaの予約ページ（幅860px）の値。購入ページもこれにそろえている。
    - item：商品（name・weight・price・画像を使う）
    - amount：この行の金額（円。数値）
    - input-id・name・value・min・max：数量の input の属性（label の for も input-id）
    - change：数量の input の wire:change に渡す式（例：updateQuantity($event.target.value)）
    - error：true のとき、数量の input の枠を赤にして aria-invalid を付ける
    - tag：行を包む要素。一覧（ul）の中では li を指定する。wire:key などの属性は行の要素に付く
    - スロット hidden：行の先頭に置く hidden の input（購入ページの item_id）
    - スロット messages：行の下に出すエラー文
--}}
<{{ $tag }} {{ $attributes->class('rounded-[10px] bg-white p-4 shadow-button md:min-h-[93px] md:px-6 md:py-4 lg:pr-6 lg:pl-21') }}>
    {{ $hidden ?? '' }}
    <div class="flex flex-col gap-3 md:min-h-[61px] md:flex-row md:items-center md:gap-4 lg:gap-0">
        <div class="flex items-center gap-4 md:contents">
            <x-item-image :item="$item" :badge="false" img-class="aspect-square" class="w-[70px] shrink-0" />

            <div class="flex min-w-0 flex-1 flex-col gap-1 md:contents">
                <p class="text-xl break-words md:min-w-0 md:flex-1 lg:ml-[53px] lg:w-[241px] lg:flex-none">{{ $item->name }} {{ $item->weight }}kg</p>
                <p class="text-base whitespace-nowrap md:w-28 md:shrink-0 lg:w-[149px]">{{ number_format($item->price) }}円(税込)</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 md:contents">
            <div class="flex items-center gap-1 md:w-32 md:shrink-0 lg:w-[138px]">
                <label class="sr-only" for="{{ $inputId }}">{{ $item->name }} {{ $item->weight }}kg の数量</label>
                <input
                    id="{{ $inputId }}"
                    name="{{ $name }}"
                    type="number"
                    min="{{ $min }}"
                    max="{{ $max }}"
                    step="1"
                    inputmode="numeric"
                    value="{{ $value }}"
                    {{ \App\Support\LivewireBoot::inputAttributes() }}
                    wire:change="{{ $change }}"
                    @if ($error) aria-invalid="true" @endif
                    @class([
                        'h-11 w-[69px] rounded-[5px] border bg-white px-2 text-center text-base '.\App\Support\LivewireBoot::READONLY_CLASSES.' md:h-[29px]',
                        'border-danger' => $error,
                        'border-line' => ! $error,
                    ])
                >
                <span class="text-base">個</span>
            </div>

            <p class="text-base whitespace-nowrap md:w-20 md:shrink-0 lg:w-auto lg:flex-1">{{ number_format($amount) }}円</p>
        </div>
    </div>

    {{ $messages ?? '' }}
</{{ $tag }}>
