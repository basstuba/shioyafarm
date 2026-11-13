{{--
    お届け先（購入ページ・予約ページ共通）。Livewire コンポーネントの WithDeliveryForm のプロパティを使う。
    氏名・電話番号は wire:model（.live なし）で、Livewire は起動時に値を欄へ書き戻す。起動前に打った文字が消えないよう、起動前は readonly（値は送信される）にする
    PCは入力欄・選択欄が幅300px（左端の位置は呼び出し側で決める）。SPは全幅で、入力欄の高さは44px
--}}
<div class="flex flex-col gap-6 md:gap-9">
    <div class="flex flex-col gap-2">
        <label class="font-maru text-xl" for="delivery_name">お届け先氏名</label>
        <input id="delivery_name" name="delivery_name" type="text" value="{{ $deliveryName }}" {{ \App\Support\LivewireBoot::inputAttributes() }} wire:model="deliveryName" autocomplete="name" @error('delivery_name') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-[18px] text-base {{ \App\Support\LivewireBoot::READONLY_CLASSES }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark md:h-10 md:w-75 @error('delivery_name') border-danger @else border-primary-dark @enderror">
        @error('delivery_name')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-maru text-xl" for="delivery_tell">お届け先電話番号</label>
        <input id="delivery_tell" name="delivery_tell" type="tel" inputmode="numeric" value="{{ $deliveryTell }}" {{ \App\Support\LivewireBoot::inputAttributes() }} wire:model="deliveryTell" autocomplete="tel" placeholder="ハイフンなしの数字10〜11桁" @error('delivery_tell') aria-invalid="true" @enderror class="h-11 w-full rounded-[10px] border bg-white px-[18px] text-base placeholder:text-line-strong {{ \App\Support\LivewireBoot::READONLY_CLASSES }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark md:h-10 md:w-75 @error('delivery_tell') border-danger @else border-primary-dark @enderror">
        @error('delivery_tell')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col gap-2">
        <label class="font-maru text-xl" for="address_id">お届け先住所</label>
        {{-- PCは選択欄の右59pxに「＋新しい住所を追加」。SPは選択欄の下に全幅で置く --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:gap-[59px]">
            <x-select
                id="address_id"
                name="address_id"
                wire:model.live="addressId"
                lock
                :error="$errors->has('address_id')"
                :aria-invalid="$errors->has('address_id') ? 'true' : null"
                :aria-describedby="collect([$errors->has('address_id') ? 'address_id-error' : null, $shippingError !== null ? 'address_id-shipping-error' : null])->filter()->implode(' ') ?: null"
                wrapper-class="w-full min-w-0 md:w-75 md:shrink-0"
                class="truncate"
            >
                @foreach ($addressOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($addressId === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </x-select>
            <x-outline-button size="address" type="button" livewire wire:click="openAddressModal" class="md:shrink-0">＋新しい住所を追加</x-outline-button>
        </div>
        @error('address_id')
            <p class="text-sm text-danger" id="address_id-error" role="alert">{{ $message }}</p>
        @enderror
        @if ($shippingError !== null)
            <p class="text-sm text-danger" id="address_id-shipping-error" role="alert" data-testid="shipping-error">{{ $shippingError }}</p>
        @endif
    </div>

    {{-- 住所の選択欄の下52px（項目間の36px＋16px）に置く。住所の文字には下線代わりに下辺の枠を付ける --}}
    <div class="flex flex-col gap-2 md:mt-4">
        <p class="font-maru text-xl">確認用お届け先住所</p>
        {{-- 住所が空のときは、下線の枠だけが短く残らないよう出さない --}}
        @if (filled($confirmAddress))
            <p class="min-h-7 w-fit max-w-full border-b border-primary-dark px-1.5 text-base leading-[27px] break-words" data-testid="confirm-address">{{ $confirmAddress }}</p>
        @endif
    </div>
</div>
