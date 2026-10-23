{{-- お届け先（購入ページ・予約ページ共通）。Livewire コンポーネントの WithDeliveryForm のプロパティを使う --}}
<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-1.5">
        <label class="font-maru text-lg md:text-xl" for="delivery_name">お届け先氏名</label>
        <input id="delivery_name" name="delivery_name" type="text" value="{{ $deliveryName }}" wire:model="deliveryName" autocomplete="name" @error('delivery_name') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-4 text-base @error('delivery_name') border-danger @else border-primary-dark @enderror">
        @error('delivery_name')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-1.5">
        <label class="font-maru text-lg md:text-xl" for="delivery_tell">お届け先電話番号</label>
        <input id="delivery_tell" name="delivery_tell" type="tel" inputmode="numeric" value="{{ $deliveryTell }}" wire:model="deliveryTell" autocomplete="tel" placeholder="ハイフンなしの数字10〜11桁" @error('delivery_tell') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-4 text-base placeholder:text-line-strong @error('delivery_tell') border-danger @else border-primary-dark @enderror">
        @error('delivery_tell')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-1.5">
        <label class="font-maru text-lg md:text-xl" for="address_id">お届け先住所</label>
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <select id="address_id" name="address_id" wire:model.live="addressId" @error('address_id') aria-invalid="true" @enderror class="h-11 w-full min-w-0 cursor-pointer md:w-auto md:flex-1 rounded-[10px] border bg-white px-4 text-base @error('address_id') border-danger @else border-primary-dark @enderror">
                @foreach ($addressOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($addressId === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
            <button type="button" wire:click="openAddressModal" class="inline-flex h-11 w-full shrink-0 cursor-pointer items-center justify-center rounded-[30px] border-2 border-action bg-white px-6 font-maru text-base text-action transition-colors duration-200 hover:border-transparent hover:bg-action-hover hover:text-action-hover-text focus-visible:border-transparent focus-visible:bg-action-hover focus-visible:text-action-hover-text focus-visible:outline-none md:w-auto">＋新しい住所を追加</button>
        </div>
        @error('address_id')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
        @if ($shippingError !== null)
            <p class="text-sm text-danger" role="alert" data-testid="shipping-error">{{ $shippingError }}</p>
        @endif
    </div>

    <div class="flex flex-col gap-1.5">
        <p class="font-maru text-lg md:text-xl">確認用お届け先住所</p>
        <p class="text-base break-words underline md:text-lg" data-testid="confirm-address">{{ $confirmAddress }}</p>
    </div>
</div>
