{{-- 住所追加モーダル（購入ページ・予約ページ共通）。フォームの外に置く。モーダル内でEnterを押しても注文が送信されないようにするため --}}
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    x-data
    x-show="$wire.showAddressModal"
    x-cloak
    x-on:keydown.escape.window="$wire.showAddressModal = false"
    role="dialog"
    aria-modal="true"
    aria-labelledby="address-modal-title"
>
    {{-- 外側（背景）を押すと閉じる --}}
    <div class="absolute inset-0 bg-black/35" x-on:click="$wire.showAddressModal = false" aria-hidden="true"></div>

    <div class="relative max-h-full w-full max-w-[480px] overflow-y-auto rounded-[20px] bg-white px-6 pt-14 pb-8 shadow-card md:px-10">
        <button type="button" class="absolute top-2 right-2 inline-flex size-11 cursor-pointer items-center justify-center rounded-full text-ink hover:bg-surface focus-visible:bg-surface focus-visible:outline-none" x-on:click="$wire.showAddressModal = false" aria-label="閉じる">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>

        <h2 id="address-modal-title" class="sr-only">新しい住所を追加</h2>

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1.5">
                <label class="font-maru text-lg" for="modal_delivery_prefecture">都道府県を選択</label>
                <select id="modal_delivery_prefecture" wire:model="delivery_prefecture" @error('delivery_prefecture') aria-invalid="true" @enderror class="h-11 w-full cursor-pointer rounded-[10px] border bg-white px-4 text-base @error('delivery_prefecture') border-danger @else border-primary-dark @enderror">
                    <option value="">選択してください</option>
                    @foreach ($prefectures as $prefecture)
                        <option value="{{ $prefecture }}">{{ $prefecture }}</option>
                    @endforeach
                </select>
                @error('delivery_prefecture')
                    <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="font-maru text-lg" for="modal_delivery_city">住所</label>
                <input id="modal_delivery_city" type="text" wire:model="delivery_city" wire:keydown.enter="addAddress" autocomplete="street-address" placeholder="例：適当市適当町３４－５６" @error('delivery_city') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-4 text-base placeholder:text-line-strong @error('delivery_city') border-danger @else border-primary-dark @enderror">
                @error('delivery_city')
                    <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-center">
                <x-outline-button type="button" size="lg" wire:click="addAddress" wire:loading.attr="disabled" wire:target="addAddress">住所を追加</x-outline-button>
            </div>
        </div>
    </div>
</div>
