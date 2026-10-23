{{-- 購入ページの注文内容・お届け先・支払方法・金額、住所追加モーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-6 rounded-[20px] bg-white px-4 py-10 text-center shadow-card">
            <p class="font-maru text-xl md:text-2xl">購入できる商品がありません</p>
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">商品一覧に戻る</a>
        </div>
    @else
        {{-- 数量・住所・支払方法の操作は Livewire、「注文を確定する」は通常のフォーム送信（エラーはサーバー側で検証して表示する） --}}
        <x-checkout-form :action="route('shop.checkout.payment')" class="flex flex-col gap-6 md:gap-8">
            <input type="hidden" name="source" value="{{ $source }}">
            {{-- 二重送信を防ぐ1回限りの注文トークン（購入ページを開いたときに発行し、再描画では変わらない） --}}
            <input type="hidden" name="order_token" value="{{ $orderToken }}">

            <div class="flex flex-col gap-6 rounded-[20px] bg-white px-4 py-6 shadow-card md:gap-8 md:px-10 md:py-10">
                <h1 class="font-maru text-2xl text-accent md:text-3xl">ご注文内容</h1>

                @error('items')
                    <p class="text-base text-danger" role="alert">{{ $message }}</p>
                @enderror

                <ul class="flex flex-col gap-4">
                    @foreach ($lines as $line)
                        @php($id = $line->item->id)
                        <li wire:key="purchase-line-{{ $id }}" class="rounded-[20px] border border-line p-4 md:px-6 md:py-4">
                            <input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $id }}">
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:gap-6">
                                <div class="min-w-0 overflow-hidden rounded-[10px] md:w-40 md:shrink-0">
                                    <x-item-image :item="$line->item" img-class="h-[160px] md:h-[100px]" />
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col gap-1 md:gap-2">
                                    <p class="font-maru text-xl break-words md:text-2xl">{{ $line->item->name }} {{ $line->item->weight }}kg</p>
                                    <p class="text-base md:text-lg">{{ number_format($line->item->price) }}円(税込)</p>
                                </div>

                                <div class="flex items-center gap-2">
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
                                        class="h-11 w-20 rounded-[10px] border-2 border-line-strong bg-white px-2 text-center text-lg"
                                    >
                                    <span class="text-base md:text-lg">個</span>
                                </div>

                                <p class="text-xl md:w-28 md:text-right md:text-2xl">{{ number_format($line->amount) }}円</p>
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

                {{-- 金額 --}}
                <dl class="flex flex-col gap-2 rounded-[10px] bg-cream-50 px-4 py-4 text-base md:ml-auto md:w-[320px] md:text-lg">
                    <div class="flex justify-between"><dt>小計</dt><dd>{{ number_format($summary->subtotal) }}円</dd></div>
                    <div class="flex justify-between"><dt>送料</dt><dd>{{ number_format($summary->shippingFee) }}円</dd></div>
                    @if ($summary->codFee > 0)
                        <div class="flex justify-between"><dt>代引手数料</dt><dd>{{ number_format($summary->codFee) }}円</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-line-strong pt-2 font-maru text-xl md:text-2xl">
                        <dt>合計</dt><dd class="text-primary">{{ number_format($summary->total) }}円</dd>
                    </div>
                </dl>

                @include('livewire.partials.delivery-fields')

                @include('livewire.partials.payment-options')
            </div>

            <div class="flex flex-col items-center gap-5 text-center">
                <p class="font-maru text-base text-primary-hover md:text-lg">ご注文内容をご確認のうえ、よろしければ「注文を確定する」ボタンを押してください</p>
                @error('order_token')
                    <p class="text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                @error('payment')
                    <p class="text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                @error('source')
                    <p class="text-base text-danger" role="alert">{{ $message }}</p>
                @enderror
                {{-- 送料を計算できない住所のときは押せない（サーバー側でも、送料を計算できない住所の注文はエラーにする） --}}
                <x-filled-button :disabled="$shippingError !== null" x-bind:disabled="submitting || {{ $shippingError !== null ? 'true' : 'false' }}" class="disabled:cursor-not-allowed disabled:opacity-60">注文を確定する</x-filled-button>
                <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">商品一覧に戻る</a>
            </div>
        </x-checkout-form>
    @endif

    @include('livewire.partials.address-modal')
</div>
