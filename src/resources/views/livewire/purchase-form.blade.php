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

                {{-- 商品の列は幅830px。金額のカードとお届け先もこの列にそろえる --}}
                <div class="mx-auto mt-6 w-full max-w-[830px] md:mt-[31px]">
                    @error('items')
                        <p class="mb-4 text-base text-danger" role="alert">{{ $message }}</p>
                    @enderror

                    <ul class="flex flex-col gap-4 md:gap-[17px]">
                        @foreach ($lines as $line)
                            @php($id = $line->item->id)
                            <li wire:key="purchase-line-{{ $id }}" class="rounded-[10px] bg-white p-4 shadow-button md:min-h-[93px] md:px-6 md:py-4 lg:pr-6 lg:pl-26">
                                <input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $id }}">
                                {{-- SPは2段（上：画像と商品名・単価／下：数量・金額）。PCは1行に並べる（md:contents で段の枠を無くす） --}}
                                <div class="flex flex-col gap-3 md:min-h-[61px] md:flex-row md:items-center md:gap-4 lg:gap-0">
                                    <div class="flex items-center gap-4 md:contents">
                                        <x-item-image :item="$line->item" :badge="false" img-class="aspect-square" class="w-[70px] shrink-0" />

                                        <div class="flex min-w-0 flex-1 flex-col gap-1 md:contents">
                                            <p class="text-xl break-words md:min-w-0 md:flex-1 lg:ml-[51px] lg:w-[189px] lg:flex-none">{{ $line->item->name }} {{ $line->item->weight }}kg</p>
                                            <p class="text-base whitespace-nowrap md:w-28 md:shrink-0 lg:w-[141px]">{{ number_format($line->item->price) }}円(税込)</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 md:contents">
                                        <div class="flex items-center gap-1 md:w-32 md:shrink-0 lg:w-[147px]">
                                            <label class="sr-only" for="purchase-quantity-{{ $id }}">{{ $line->item->name }} {{ $line->item->weight }}kg の数量</label>
                                            <input
                                                id="purchase-quantity-{{ $id }}"
                                                name="items[{{ $loop->index }}][quantity]"
                                                type="number"
                                                min="{{ $minQuantity }}"
                                                max="{{ $maxQuantity }}"
                                                step="1"
                                                inputmode="numeric"
                                                value="{{ $quantities[$id] }}"
                                                wire:change="updateQuantity({{ $id }}, $event.target.value)"
                                                class="h-11 w-[69px] rounded-[5px] border border-line bg-white px-2 text-center text-base md:h-[29px]"
                                            >
                                            <span class="text-base">個</span>
                                        </div>

                                        <p class="text-base whitespace-nowrap md:w-20 md:shrink-0 lg:w-auto lg:flex-1">{{ number_format($line->amount) }}円</p>
                                    </div>
                                </div>

                                @error('quantities.'.$id)
                                    <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                @enderror
                                @error('items.'.$loop->index.'.quantity')
                                    <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                @enderror
                                @error('items.'.$loop->index.'.item_id')
                                    <p class="mt-3 text-base text-danger" role="alert">{{ $message }}</p>
                                @enderror
                            </li>
                        @endforeach
                    </ul>

                    {{-- 金額。PCは幅200で列の右端、最後の商品の17px下。代引のときは行が増えて高さが伸びる --}}
                    <dl class="mt-4 rounded-[10px] bg-white px-[15px] py-[14px] text-base leading-6 shadow-button md:mt-[17px] md:ml-auto md:w-50">
                        <div class="flex justify-between px-[9px] text-muted"><dt>小計</dt><dd>{{ number_format($summary->subtotal) }}円</dd></div>
                        <div class="mt-[14px] flex justify-between px-[9px] text-muted"><dt>送料</dt><dd>{{ number_format($summary->shippingFee) }}円</dd></div>
                        @if ($summary->codFee > 0)
                            <div class="mt-[14px] flex justify-between px-[9px] text-muted"><dt>代引手数料</dt><dd>{{ number_format($summary->codFee) }}円</dd></div>
                        @endif
                        <div class="mt-4 flex justify-between border-t border-muted px-[9px] pt-[19px] text-subtotal">
                            <dt>合計</dt><dd>{{ number_format($summary->total) }}円</dd>
                        </div>
                    </dl>

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
