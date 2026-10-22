{{-- 購入ページの注文内容・お届け先・支払方法・金額、住所追加モーダル。見た目は仮置き（Figmaの上限リセット後に仕上げる） --}}
<div>
    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-6 rounded-[20px] bg-white px-4 py-10 text-center shadow-card">
            <p class="font-maru text-xl md:text-2xl">購入できる商品がありません</p>
            <a href="{{ route('shop.items.index') }}" class="inline-flex min-h-11 items-center font-maru text-base underline md:text-lg">商品一覧に戻る</a>
        </div>
    @else
        {{-- 数量・住所・支払方法の操作は Livewire、「注文を確定する」は通常のフォーム送信（エラーはサーバー側で検証して表示する）。
             送信中は「注文を確定する」を押せなくする（二重送信の防止。ブラウザの「戻る」で戻ったときは押せる状態に戻す） --}}
        <form method="POST" action="{{ route('shop.checkout.payment') }}" novalidate class="flex flex-col gap-6 md:gap-8" x-data="{ submitting: false }" x-on:submit="submitting = true" x-on:pageshow.window="submitting = false">
            @csrf
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

                {{-- お届け先 --}}
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

                {{-- 支払方法 --}}
                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-3 font-maru text-lg md:text-xl">お支払い方法を選択してください</legend>
                    <div class="grid gap-3 md:grid-cols-3 md:gap-4">
                        @foreach ($paymentOptions as $option)
                            <label class="flex min-h-20 cursor-pointer items-center gap-3 rounded-[10px] border-primary-dark px-4 py-3 border bg-white has-[input:checked]:border-4 has-[input:checked]:bg-cream-200 has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-primary">
                                <input type="radio" name="payment_method" value="{{ $option['value'] }}" wire:model.live="paymentMethod" @checked($paymentMethod === $option['value']) class="size-5 shrink-0 accent-primary">
                                <img class="size-10 shrink-0 object-contain" src="{{ asset('storage/'.$option['icon']) }}" alt="" aria-hidden="true">
                                <span class="flex min-w-0 flex-col">
                                    <span class="font-maru text-base md:text-lg">{{ $option['label'] }}</span>
                                    <span class="text-sm break-words">{{ $option['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')
                        <p class="text-sm text-danger" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>
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
        </form>
    @endif

    {{-- 住所追加モーダル（フォームの外に置く。モーダル内でEnterを押しても注文が送信されないようにするため） --}}
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
</div>
