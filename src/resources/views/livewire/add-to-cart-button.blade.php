{{-- 「カートに入れる」ボタンと、結果を表示するモーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    <x-outline-button size="lg" type="button" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart">カートに入れる</x-outline-button>

    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-data
        x-show="$wire.open"
        x-cloak
        x-on:keydown.escape.window="$wire.open = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-modal-message-{{ $itemId }}"
    >
        {{-- 外側（背景）を押すと閉じる --}}
        <div class="absolute inset-0 bg-black/35" x-on:click="$wire.open = false" aria-hidden="true"></div>

        <div class="relative w-full max-w-[480px] rounded-[20px] bg-white px-6 pt-14 pb-8 shadow-card md:px-10">
            <button type="button" class="absolute top-2 right-2 inline-flex size-11 cursor-pointer items-center justify-center rounded-full text-ink hover:bg-surface focus-visible:bg-surface focus-visible:outline-none" x-on:click="$wire.open = false" aria-label="閉じる">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>

            <p id="cart-modal-message-{{ $itemId }}" @class(['text-center font-maru text-xl leading-[1.6] md:text-2xl', 'text-danger' => ! $added])>{{ $message }}</p>

            <div class="mt-8 flex flex-col items-center gap-4">
                @unless ($rejected)
                    <x-filled-button :href="route('shop.cart')">カートを見る</x-filled-button>
                @endunless
                <x-outline-button size="lg" :href="route('shop.items.index')">買い物を続ける</x-outline-button>
            </div>
        </div>
    </div>
</div>
