{{-- カートの商品一覧 --}}
<div>
    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-6 rounded-[10px] bg-white px-4 py-10 text-center shadow-button">
            <p class="font-maru text-xl">カートに商品はありません</p>
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline">買い物を続ける</a>
        </div>
    @else
        <ul class="flex flex-col gap-[15px]">
            @foreach ($lines as $line)
                <li wire:key="cart-line-{{ $line->item->id }}" class="rounded-[10px] bg-white p-4 shadow-button md:min-h-[93px] md:px-6 md:py-4 lg:pr-16 lg:pl-17">
                    {{-- SPは2段（上：画像と商品名・単価／下：数量・金額・削除）。PCは1行に並べる（md:contents で段の枠を無くす） --}}
                    <div class="flex flex-col gap-3 md:min-h-[61px] md:flex-row md:items-center md:gap-4 lg:gap-0">
                        <div class="flex items-center gap-4 md:contents">
                            <x-item-image :item="$line->item" :badge="false" img-class="aspect-square" class="w-[70px] shrink-0" />

                            <div class="flex min-w-0 flex-1 flex-col gap-1 md:contents">
                                <p class="font-maru text-xl break-words md:min-w-0 md:flex-1 lg:ml-[51px] lg:w-[189px] lg:flex-none">{{ $line->item->name }} {{ $line->item->weight }}kg</p>
                                <p class="text-base whitespace-nowrap md:w-28 md:shrink-0 lg:w-30">{{ number_format($line->item->price) }}円(税込)</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 md:contents">
                            <div class="flex items-center gap-1 md:w-35 md:shrink-0 lg:w-[182px]">
                                <label class="sr-only" for="cart-quantity-{{ $line->item->id }}">{{ $line->item->name }} {{ $line->item->weight }}kg の数量</label>
                                <input
                                    id="cart-quantity-{{ $line->item->id }}"
                                    wire:key="cart-quantity-{{ $line->item->id }}-{{ $errorCounts[$line->item->id] ?? 0 }}"
                                    type="number"
                                    min="{{ \App\Services\CartService::MIN_QUANTITY }}"
                                    max="{{ \App\Services\CartService::MAX_QUANTITY }}"
                                    step="1"
                                    inputmode="numeric"
                                    value="{{ $line->quantity }}"
                                    wire:change="updateQuantity({{ $line->item->id }}, $event.target.value)"
                                    class="h-11 w-[69px] rounded-[5px] border border-line bg-white px-2 text-center text-base md:h-[29px]"
                                >
                                <span class="text-base">個</span>
                            </div>

                            <p class="text-base whitespace-nowrap md:w-20 md:shrink-0 lg:w-[146px]">{{ number_format($line->amount) }}円</p>

                            <button type="button" wire:click="remove({{ $line->item->id }})" class="inline-flex h-11 w-[86px] shrink-0 cursor-pointer items-center justify-center gap-1 rounded-[10px] bg-delete font-maru text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-delete-hover hover:shadow-button focus-visible:bg-delete-hover focus-visible:shadow-button focus-visible:outline-none md:h-[30px]">
                                <img class="size-5" src="{{ asset('storage/image/icon/white-dustbin-icon.webp') }}" alt="" aria-hidden="true">
                                削除
                            </button>
                        </div>
                    </div>

                    @error('quantity.'.$line->item->id)
                        <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </li>
            @endforeach
        </ul>

        {{-- 小計。PCは右寄せの421×82、SPは全幅で小計の下に購入ボタン --}}
        <div class="mt-[15px] flex flex-col gap-3 rounded-[10px] bg-white p-4 shadow-button md:ml-auto md:h-[82px] md:w-[421px] md:flex-row md:items-center md:justify-between md:px-6 md:py-0">
            <p class="flex items-center gap-11 text-base">小計<span class="text-subtotal">{{ number_format($subtotal) }}円</span></p>
            <x-filled-button size="sm" :href="route('shop.checkout.index')">購入する</x-filled-button>
        </div>

        {{-- PCでは購入ボタン（右端から24px、幅200px）の真下に置く --}}
        <div class="mt-[15px] text-center md:mr-6 md:ml-auto md:w-50">
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline">買い物を続ける</a>
        </div>
    @endif
</div>
