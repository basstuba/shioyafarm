{{-- 商品画像。予約限定の商品には右上に「予約限定」のバッジを付ける。imgClass で画像の高さを変えられる --}}
@props(['item', 'imgClass' => 'h-[200px]'])
<div class="relative">
    <img class="{{ $imgClass }} w-full object-cover" src="{{ asset('storage/' . $item->img_url) }}" alt="{{ $item->name }} {{ $item->weight }}kg">
    @if ($item->is_reserved_only)
        <span class="absolute top-3 right-3 rounded-[30px] bg-primary px-4 py-1.5 text-lg text-white md:top-[15px] md:right-[17px] md:px-[22px] md:py-2.5 md:text-2xl md:leading-none">予約限定</span>
    @endif
</div>
