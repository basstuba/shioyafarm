{{-- 購入ページの注文内容・お届け先・支払方法・金額、住所追加モーダル --}}
<div>
    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-6 rounded-[20px] bg-white px-4 py-10 text-center shadow-panel md:rounded-[30px]">
            <p class="font-maru text-xl md:text-2xl">購入できる商品がありません</p>
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline">商品一覧に戻る</a>
        </div>
    @else
        {{-- 数量・住所・支払方法の操作は Livewire、「注文を確定する」は通常のフォーム送信（エラーはサーバー側で検証して表示する） --}}
        <x-checkout-form :action="route('shop.checkout.payment')">
            <input type="hidden" name="source" value="{{ $source }}">
            {{-- 二重送信を防ぐ1回限りの注文トークン（購入ページを開いたときに発行し、再描画では変わらない） --}}
            <input type="hidden" name="order_token" value="{{ $orderToken }}">

            <div class="rounded-[20px] bg-white px-4 py-8 shadow-panel md:rounded-[30px] md:px-6 md:pt-[49px] md:pb-[86px]">
                <h1 class="text-center font-maru text-2xl text-accent md:text-[32px]">ご注文内容</h1>

                {{-- 商品の列は幅860px（予約ページと同じ）。金額のカードとお届け先もこの列にそろえる --}}
                <div class="mx-auto mt-6 w-full max-w-[860px] md:mt-[31px]">
                    @error('items')
                        <p class="mb-4 text-base text-danger" role="alert">{{ $message }}</p>
                    @enderror

                    <ul class="flex flex-col gap-4 md:gap-[17px]">
                        @foreach ($lines as $line)
                            @php($id = $line->item->id)
                            <x-checkout-line
                                tag="li"
                                wire:key="purchase-line-{{ $id }}"
                                :item="$line->item"
                                :amount="$line->amount"
                                input-id="purchase-quantity-{{ $id }}"
                                name="items[{{ $loop->index }}][quantity]"
                                :value="$quantities[$id]"
                                :min="$minQuantity"
                                :max="$maxQuantity"
                                change="updateQuantity({{ $id }}, $event.target.value)"
                                :error="$errors->has('quantities.'.$id) || $errors->has('items.'.$loop->index.'.quantity')"
                            >
                                <x-slot:hidden>
                                    <input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $id }}">
                                </x-slot:hidden>
                                <x-slot:messages>
                                    @error('quantities.'.$id)
                                        <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                    @enderror
                                    @error('items.'.$loop->index.'.quantity')
                                        <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                    @enderror
                                    @error('items.'.$loop->index.'.item_id')
                                        <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                    @enderror
                                </x-slot:messages>
                            </x-checkout-line>
                        @endforeach
                    </ul>

                    {{-- 金額。PCは最後の商品の17px下。代引のときは行が増えて高さが伸びる --}}
                    @include('livewire.partials.order-summary', ['topClass' => 'md:mt-[17px]'])

                    {{-- お届け先は左端を商品の列の左から265pxにそろえる（md〜lgは入りきらないので左余白を詰める） --}}
                    <div class="mt-8 md:mt-[14px] md:pl-12 lg:pl-[265px]">
                        @include('livewire.partials.delivery-fields')
                    </div>

                    <div class="mt-12 md:mt-[67px]">
                        @include('livewire.partials.payment-options')
                    </div>
                </div>
            </div>

            <div class="mt-8 flex flex-col items-center text-center md:mt-[73px]">
                <p class="font-maru text-base text-primary-text md:text-xl">ご注文内容をご確認のうえ、よろしければ「注文を確定する」ボタンを押してください</p>
                @error('order_token')
                    <p class="mt-4 text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                @error('payment')
                    <p class="mt-4 text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                @error('source')
                    <p class="mt-4 text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                {{-- 送料を計算できない住所のときは押せない（サーバー側でも、送料を計算できない住所の注文はエラーにする） --}}
                <x-filled-button size="2xl" class="mt-8 md:mt-[54px]" :disabled="$shippingError !== null" x-bind:disabled="submitting || {{ $shippingError !== null ? 'true' : 'false' }}">注文を確定する</x-filled-button>
                <a href="{{ route('shop.items.index') }}" class="mt-8 inline-flex min-h-11 items-center font-maru text-base underline md:mt-[66px]">商品一覧に戻る</a>
            </div>
        </x-checkout-form>
    @endif

    @include('livewire.partials.address-modal')
</div>
