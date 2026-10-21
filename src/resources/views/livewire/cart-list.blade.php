{{-- カートの商品一覧。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-6 rounded-[20px] bg-white px-4 py-10 text-center shadow-card">
            <p class="font-maru text-xl md:text-2xl">カートに商品はありません</p>
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">買い物を続ける</a>
        </div>
    @else
        <ul class="flex flex-col gap-4 md:gap-6">
            @foreach ($lines as $line)
                <li wire:key="cart-line-{{ $line->item->id }}" class="rounded-[20px] bg-white p-4 shadow-card md:px-8 md:py-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:gap-8">
                        <div class="min-w-0 overflow-hidden rounded-[10px] md:w-40 md:shrink-0">
                            <x-item-image :item="$line->item" img-class="h-[160px] md:h-[100px]" />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1 md:gap-2">
                            <p class="font-maru text-xl break-words md:text-2xl">{{ $line->item->name }} {{ $line->item->weight }}kg</p>
                            <p class="text-base md:text-lg">{{ number_format($line->item->price) }}円(税込)</p>
                        </div>

                        <div class="flex items-center gap-2">
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
                                class="h-11 w-20 rounded-[10px] border-2 border-line-strong bg-white px-2 text-center text-lg"
                            >
                            <span class="text-base md:text-lg">個</span>
                        </div>

                        <p class="text-xl md:w-28 md:text-right md:text-2xl">{{ number_format($line->amount) }}円</p>

                        <button type="button" wire:click="remove({{ $line->item->id }})" class="inline-flex h-11 min-w-24 cursor-pointer items-center justify-center gap-1 rounded-[30px] bg-danger px-4 font-maru text-base text-white transition-opacity hover:opacity-80 focus-visible:opacity-80 focus-visible:outline-none">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3" />
                            </svg>
                            削除
                        </button>
                    </div>

                    @error('quantity.'.$line->item->id)
                        <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex flex-col items-center gap-4 rounded-[20px] bg-white px-4 py-6 shadow-card md:ml-auto md:max-w-[480px] md:flex-row md:justify-between md:px-8">
            <p class="font-maru text-xl md:text-2xl">小計 <span class="text-primary">{{ number_format($subtotal) }}円</span></p>
            {{-- 購入機能ができたら href を差し替える --}}
            <x-filled-button href="#">購入する</x-filled-button>
        </div>

        <div class="mt-6 text-center md:mt-8">
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">買い物を続ける</a>
        </div>
    @endif
</div>
