{{-- 「カートに入れる」ボタンと、結果を表示するモーダル --}}
<div>
    <x-outline-button size="xl" type="button" livewire wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart">カートに入れる</x-outline-button>

    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-data
        x-show="$wire.open"
        x-cloak
        x-trap.noscroll="$wire.open"
        x-on:keydown.escape.window="$wire.open = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-modal-message-{{ $itemId }}"
    >
        {{-- 外側（背景）を押すと閉じる --}}
        <div class="absolute inset-0 bg-black/35" x-on:click="$wire.open = false" aria-hidden="true"></div>

        <div class="relative flex w-full max-w-100 flex-col items-center rounded-[30px] bg-white px-4 pt-[25px] pb-3 text-ink shadow-panel">
            <p id="cart-modal-message-{{ $itemId }}" @class(['text-center font-maru text-xl break-words', 'text-danger' => ! $added])>{{ $message }}</p>

            @unless ($rejected)
                <x-outline-button size="md" class="mt-[29px]" :href="route('shop.cart')">カートの中を確認する</x-outline-button>
            @endunless

            <button type="button" class="mt-3 inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 font-maru text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="$wire.open = false">閉じる</button>
        </div>
    </div>
</div>
