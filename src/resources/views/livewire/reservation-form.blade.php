{{-- 予約ページの予約内容・お届け先・支払方法・金額、住所追加モーダル --}}
<div>
    {{-- 数量・住所・支払方法の操作は Livewire、「予約を確定する」は通常のフォーム送信（エラーはサーバー側で検証して表示する） --}}
    <x-checkout-form :action="route('shop.reservation.payment')">
        <input type="hidden" name="item_id" value="{{ $item->id }}">
        {{-- 二重送信を防ぐ1回限りの注文トークン（予約ページを開いたときに発行し、再描画では変わらない） --}}
        <input type="hidden" name="order_token" value="{{ $orderToken }}">

        <div class="rounded-[20px] bg-white px-4 py-8 shadow-panel md:rounded-[30px] md:px-6 md:pt-[49px] md:pb-[86px]">
            <h1 class="text-center font-maru text-2xl text-accent md:text-[32px]">ご予約内容</h1>

            {{-- 商品の列は幅860px。金額のカードとお届け先もこの列にそろえる --}}
            <div class="mx-auto mt-6 w-full max-w-[860px] md:mt-[31px]">
                @error('item_id')
                    <p class="mb-4 text-base text-danger" role="alert">{{ $message }}</p>
                @enderror

                <x-checkout-line
                    :item="$item"
                    :amount="$summary->subtotal"
                    input-id="reservation-quantity"
                    name="quantity"
                    :value="$quantity"
                    :min="$minQuantity"
                    :max="$maxQuantity"
                    change="updateQuantity($event.target.value)"
                    :error="$errors->has('quantity')"
                >
                    <x-slot:messages>
                        @error('quantity')
                            <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </x-slot:messages>
                </x-checkout-line>

                {{-- 金額。PCは商品の行の19px下。予約は代引手数料の行が出ない（codFee が 0） --}}
                @include('livewire.partials.order-summary', ['topClass' => 'md:mt-[19px]'])

                {{-- お届け先は左端を商品の列の左から280pxにそろえる（md〜lgは入りきらないので左余白を詰める） --}}
                <div class="mt-8 md:mt-[14px] md:pl-12 lg:pl-[280px]">
                    @include('livewire.partials.delivery-fields')
                </div>

                <div class="mt-12 md:mt-[67px]">
                    @include('livewire.partials.payment-options')
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-col items-center text-center md:mt-[61px]">
            <p class="font-maru text-base text-primary-text md:text-xl">ご予約内容をご確認のうえ、よろしければ「予約を確定する」ボタンを押してください</p>
            @error('order_token')
                <p class="mt-4 text-base text-danger" role="alert">{{ $message }}</p>
            @enderror
            @error('payment')
                <p class="mt-4 text-base text-danger" role="alert">{{ $message }}</p>
            @enderror
            {{-- 送料を計算できない住所のときは押せない（サーバー側でも、送料を計算できない住所の予約はエラーにする） --}}
            <x-filled-button size="2xl" variant="reserve" class="mt-8 md:mt-[54px]" :disabled="$shippingError !== null" x-bind:disabled="submitting || {{ $shippingError !== null ? 'true' : 'false' }}">予約を確定する</x-filled-button>
            <a href="{{ route('shop.items.index') }}" class="mt-8 inline-flex min-h-11 items-center font-maru text-base underline md:mt-[66px]">商品一覧に戻る</a>
        </div>
    </x-checkout-form>

    @include('livewire.partials.address-modal')
</div>
