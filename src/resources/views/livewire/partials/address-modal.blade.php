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

    {{-- 幅700px。中身は幅404pxの列を中央に置く（SPは左右16pxの余白で画面に収める） --}}
    <div class="relative max-h-full w-full max-w-175 overflow-y-auto rounded-[30px] bg-white px-6 pt-10 pb-6 text-ink shadow-panel md:px-0 md:pt-[61px] md:pb-[58px]">
        <h2 id="address-modal-title" class="sr-only">新しい住所を追加</h2>

        <div class="mx-auto flex w-full max-w-101 flex-col">
            <div class="flex flex-col gap-1.5">
                <label class="font-maru text-xl" for="modal_delivery_prefecture">都道府県を選択</label>
                <x-select
                    id="modal_delivery_prefecture"
                    wire:model="delivery_prefecture"
                    required
                    :error="$errors->has('delivery_prefecture')"
                    :aria-invalid="$errors->has('delivery_prefecture') ? 'true' : null"
                    :aria-describedby="$errors->has('delivery_prefecture') ? 'modal_delivery_prefecture-error' : null"
                    wrapper-class="w-full md:w-75"
                >
                    <option value="">選択してください</option>
                    @foreach ($prefectures as $prefecture)
                        <option class="text-ink" value="{{ $prefecture }}">{{ $prefecture }}</option>
                    @endforeach
                </x-select>
                @error('delivery_prefecture')
                    <p class="text-sm text-danger" id="modal_delivery_prefecture-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-8 flex flex-col gap-1.5 md:mt-11">
                <label class="font-maru text-xl" for="modal_delivery_city">住所</label>
                <input id="modal_delivery_city" type="text" wire:model="delivery_city" wire:keydown.enter="addAddress" autocomplete="street-address" placeholder="例：適当市適当町３４－５６" @error('delivery_city') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-[18px] text-base placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark md:h-10 @error('delivery_city') border-danger @else border-primary-dark @enderror">
                @error('delivery_city')
                    <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>

            {{-- 入力欄の下79px。「住所を追加」の下に、文字だけの「閉じる」（カート追加のモーダルと同じ） --}}
            <div class="mx-auto mt-10 w-40 md:mt-[79px]">
                <x-outline-button size="address" type="button" wire:click="addAddress" wire:loading.attr="disabled" wire:target="addAddress">住所を追加</x-outline-button>
            </div>
            <button type="button" class="mx-auto mt-3 inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center px-4 font-maru text-base hover:underline focus-visible:underline focus-visible:outline-none" x-on:click="$wire.showAddressModal = false">閉じる</button>
        </div>
    </div>
</div>
