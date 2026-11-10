{{--
    管理画面の掲載商品の登録・修正の共通フォーム（Figma 掲載商品登録 174:56・掲載商品修正 180:21・item-update-button 182:28）。
    props（mode・item）の説明と、入力欄の初期値（old() の値を優先）・クラスの組み立ては App\View\Components\AdminItemForm にある。
    カード（幅800px・角丸30・影。PCの内側の余白108px、SPは px-4）は mail_create と同じ形。入力欄の幅はFigmaの値（商品名400・金額200・紹介文584・予約可能数の数200）、SPは全幅。
    Alpine は Livewire 同梱のものを使う。
    - reservedOnly（'1' 限定／'0' 通常／'' 未選択）と limitType（'unlimited'／'limited'／''）：予約可能数の部品全体を fieldset の disabled で無効（グレー）にする条件と、上限の数を入力できる条件
    - previewUrl：画像のプレビュー（選んだ・落とした画像。修正は今の画像から始まる。未選択は「未設定」）。ドロップした画像は DataTransfer で input[type=file] に入れて送る
    - sending：送信中はボタンを押せなくして二重送信を防ぐ（「戻る」でページが復元されたときは pageshow で戻す）
    無効のとき（通常・未選択）は、予約可能数の項目は送られず、サーバー側でも通常なら数を捨てる。
--}}
@use('App\Http\Requests\ItemStoreRequest')
<form
    class="mt-6 w-full max-w-[800px] rounded-[30px] bg-white px-4 py-8 shadow-card lg:px-27 lg:py-10"
    action="{{ $action() }}"
    method="POST"
    enctype="multipart/form-data"
    novalidate
    x-data="{
        reservedOnly: @js($reservedOnly),
        limitType: @js($limitType),
        currentUrl: @js($currentUrl),
        previewUrl: @js($currentUrl),
        objectUrl: null,
        imageError: '',
        over: false,
        sending: false,
        maxBytes: {{ ItemStoreRequest::IMAGE_MAX_KB * 1024 }},
        types: ['image/jpeg', 'image/png', 'image/webp'],
        reject(message) {
            this.$refs.file.value = ''
            this.revoke()
            this.previewUrl = this.currentUrl
            this.imageError = message
        },
        revoke() {
            if (this.objectUrl) { URL.revokeObjectURL(this.objectUrl); this.objectUrl = null }
        },
        choose(file) {
            this.revoke()
            this.imageError = ''
            if (! file) {
                this.previewUrl = this.currentUrl
                return
            }
            if (! this.types.includes(file.type) || file.size > this.maxBytes) {
                this.reject(@js(ItemStoreRequest::IMAGE_HINT))
                return
            }
            this.objectUrl = URL.createObjectURL(file)
            this.previewUrl = this.objectUrl
        },
        drop(event) {
            this.over = false
            const file = event.dataTransfer.files[0]
            if (! file) { return }
            if (! this.types.includes(file.type) || file.size > this.maxBytes) {
                this.reject(@js(ItemStoreRequest::IMAGE_HINT))
                return
            }
            try {
                const transfer = new DataTransfer()
                transfer.items.add(file)
                this.$refs.file.files = transfer.files
            } catch (e) {
                this.reject('「画像を選択」から選んでください')
                return
            }
            this.choose(file)
        },
    }"
    x-on:submit="if (sending) { $event.preventDefault(); return }; sending = true"
    x-on:pageshow.window="sending = false"
    x-on:dragover.window.prevent
    x-on:drop.window.prevent
>
    @csrf
    @if ($isEdit())
        <input type="hidden" name="id" value="{{ $item->id }}">
    @endif

    <div class="flex flex-col gap-7.5">
        {{-- 商品名 --}}
        <div class="flex flex-col gap-2">
            <label class="{{ $labelClass() }}" for="name">商品名</label>
            <input
                class="{{ $inputClass() }} h-11 w-full lg:h-10 lg:w-[400px] {{ $borderClass($errors->has('name')) }}"
                id="name"
                name="name"
                type="text"
                value="{{ $name }}"
                placeholder="例：じゃがいも"
                autocomplete="off"
                @if ($errors->has('name')) aria-invalid="true" aria-describedby="name-error" @endif
            />
            @error('name')
                <p id="name-error" class="{{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </div>

        {{-- 商品重量 --}}
        <fieldset class="min-w-0" @if ($errors->has('weight')) aria-describedby="weight-error" @endif>
            <legend class="mb-2 p-0 {{ $labelClass() }}">商品重量</legend>
            <div class="flex flex-wrap gap-x-6">
                @foreach ([2, 5, 10] as $option)
                    <x-admin-radio name="weight" :value="$option" :checked="$weight === (string) $option">{{ $option }}kg</x-admin-radio>
                @endforeach
            </div>
            @error('weight')
                <p id="weight-error" class="mt-2 {{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </fieldset>

        {{-- 商品金額 --}}
        <div class="flex flex-col gap-2">
            <label class="{{ $labelClass() }}" for="price">商品金額</label>
            <div class="flex items-center gap-2">
                <input
                    class="{{ $inputClass() }} h-11 min-w-0 flex-1 lg:h-10 lg:w-[200px] lg:flex-none {{ $borderClass($errors->has('price')) }}"
                    id="price"
                    name="price"
                    type="text"
                    inputmode="numeric"
                    value="{{ $price }}"
                    placeholder="例：330"
                    autocomplete="off"
                    @if ($errors->has('price')) aria-invalid="true" aria-describedby="price-error" @endif
                />
                <span class="shrink-0 text-base text-ink">円(税込)</span>
            </div>
            @error('price')
                <p id="price-error" class="{{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </div>

        {{-- 商品画像とプレビュー（SPはプレビューが画像の枠の下） --}}
        <div class="flex flex-col gap-7.5 lg:flex-row lg:gap-10">
            <div class="flex min-w-0 flex-col gap-2 lg:w-[300px] lg:shrink-0">
                <span id="image-label" class="{{ $labelClass() }}">商品画像</span>
                <div
                    class="flex h-[100px] w-full flex-col items-center justify-center gap-2 rounded-[10px] border border-dashed bg-white px-2 transition-colors {{ $borderClass($errors->has('image')) }}"
                    x-bind:class="over ? 'border-ink bg-admin-bar' : ''"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="drop($event)"
                >
                    <p class="text-center text-sm text-ink" id="image-note">ここに画像をドラッグ＆ドロップ または</p>
                    <button
                        class="inline-flex h-11 cursor-pointer items-center justify-center rounded-[5px] bg-admin-gray px-4 text-sm text-white transition-colors hover:bg-admin-dark focus-visible:bg-admin-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink lg:h-8"
                        type="button"
                        aria-describedby="image-note"
                        x-on:click="$refs.file.click()"
                    >画像を選択</button>
                </div>
                <input
                    class="sr-only"
                    id="image"
                    name="image"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    tabindex="-1"
                    aria-labelledby="image-label"
                    x-ref="file"
                    x-on:change="choose($event.target.files[0])"
                    aria-describedby="image-client-error{{ $errors->has('image') ? ' image-error' : '' }}"
                    @if ($errors->has('image')) aria-invalid="true" @endif
                />
                {{-- ブラウザで確かめた形式・大きさの誤り（imageError）と、サーバーの検証のエラー（画像を選び直すまで出す） --}}
                <p id="image-client-error" class="{{ $errorClass() }}" role="alert" x-cloak x-show="imageError !== ''" x-text="imageError" style="display: none"></p>
                @error('image')
                    <p id="image-error" class="{{ $errorClass() }}" x-show="imageError === ''">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <span class="{{ $labelClass() }}">プレビュー</span>
                <div class="h-[100px] w-[170px] overflow-hidden bg-admin-preview">
                    <img
                        class="size-full object-cover"
                        src="{{ $currentUrl }}"
                        alt="選んだ商品画像のプレビュー"
                        x-cloak
                        x-show="previewUrl"
                        x-bind:src="previewUrl"
                        @if ($currentUrl === '') style="display: none" @endif
                    >
                    <div
                        class="flex size-full items-center justify-center text-xl font-bold text-muted"
                        x-cloak
                        x-show="! previewUrl"
                        @if ($currentUrl !== '') style="display: none" @endif
                    >未設定</div>
                </div>
            </div>
        </div>

        {{-- 商品紹介文 --}}
        <div class="flex flex-col gap-2">
            <label class="{{ $labelClass() }}" for="detail">商品紹介文</label>
            <textarea
                class="{{ $inputClass() }} h-[120px] w-full resize-y py-3 lg:h-[100px] {{ $borderClass($errors->has('detail')) }}"
                id="detail"
                name="detail"
                placeholder="例：ホクホク食感と優しい甘みが特徴の新鮮なじゃがいもです"
                @if ($errors->has('detail')) aria-invalid="true" aria-describedby="detail-error" @endif
            >{{ $detail }}</textarea>
            @error('detail')
                <p id="detail-error" class="{{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </div>

        {{-- おすすめ商品 --}}
        <fieldset class="min-w-0" @if ($errors->has('recommend')) aria-describedby="recommend-error" @endif>
            <legend class="mb-2 p-0 {{ $labelClass() }}">おすすめ商品</legend>
            <div class="flex flex-wrap gap-x-6">
                <x-admin-radio name="recommend" value="1" :checked="$recommend === '1'">はい</x-admin-radio>
                <x-admin-radio name="recommend" value="0" :checked="$recommend === '0'">いいえ</x-admin-radio>
            </div>
            @error('recommend')
                <p id="recommend-error" class="mt-2 {{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </fieldset>

        {{-- 予約限定 --}}
        <fieldset class="min-w-0" @if ($errors->has('is_reserved_only')) aria-describedby="is_reserved_only-error" @endif>
            <legend class="mb-2 p-0 {{ $labelClass() }}">予約限定</legend>
            <div class="flex flex-wrap gap-x-6">
                <x-admin-radio name="is_reserved_only" value="1" :checked="$reservedOnly === '1'" x-model="reservedOnly">限定</x-admin-radio>
                <x-admin-radio name="is_reserved_only" value="0" :checked="$reservedOnly === '0'" x-model="reservedOnly">通常</x-admin-radio>
            </div>
            @error('is_reserved_only')
                <p id="is_reserved_only-error" class="mt-2 {{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </fieldset>

        {{-- 予約可能数（予約限定で「限定」のときだけ設定できる。通常・未選択のときは部品全体を無効にする） --}}
        <div>
            <fieldset
                class="min-w-0 transition-opacity disabled:opacity-40"
                @if ($reservedOnly !== '1') disabled @endif
                x-bind:disabled="reservedOnly !== '1'"
                aria-describedby="reservation-note"
            >
                <legend class="mb-2 p-0 {{ $labelClass() }}">予約可能数</legend>
                <div class="flex flex-wrap items-center gap-x-6">
                    <x-admin-radio name="reservation_limit_type" :value="ItemStoreRequest::LIMIT_UNLIMITED" :checked="$limitType === ItemStoreRequest::LIMIT_UNLIMITED" x-model="limitType">上限なし</x-admin-radio>
                    <x-admin-radio name="reservation_limit_type" :value="ItemStoreRequest::LIMIT_LIMITED" :checked="$limitType === ItemStoreRequest::LIMIT_LIMITED" x-model="limitType">上限あり</x-admin-radio>
                    <div class="flex w-full items-center gap-2 lg:w-auto">
                        <label class="sr-only" for="reservation_limit">予約可能数（上限ありのときの数）</label>
                        <input
                            class="{{ $inputClass() }} h-11 min-w-0 flex-1 lg:h-10 lg:w-[200px] lg:flex-none {{ $borderClass($errors->has('reservation_limit')) }}"
                            id="reservation_limit"
                            name="reservation_limit"
                            type="text"
                            inputmode="numeric"
                            value="{{ $reservationLimit }}"
                            placeholder="例：200"
                            autocomplete="off"
                            @if ($limitType !== ItemStoreRequest::LIMIT_LIMITED) disabled @endif
                            x-bind:disabled="limitType !== 'limited'"
                            @if ($errors->has('reservation_limit')) aria-invalid="true" aria-describedby="reservation_limit-error" @endif
                        />
                        <span class="shrink-0 text-base text-ink">個</span>
                    </div>
                </div>
            </fieldset>
            <p id="reservation-note" class="mt-2 text-base text-muted" x-cloak x-show="reservedOnly === '0'" @if ($reservedOnly !== '0') style="display: none" @endif>通常の商品は予約を受け付けません</p>
            <p class="mt-2 text-base text-muted" x-cloak x-show="reservedOnly === ''" @if ($reservedOnly !== '') style="display: none" @endif>予約限定で「限定」を選ぶと設定できます</p>
            @error('reservation_limit_type')
                <p id="reservation_limit_type-error" class="mt-2 {{ $errorClass() }}">{{ $message }}</p>
            @enderror
            @error('reservation_limit')
                <p id="reservation_limit-error" class="mt-2 {{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- 419（ページの有効期限切れ）で戻ってきたときの案内（赤字） --}}
    @error('form')
        <p class="mt-6 {{ $errorClass() }}" role="alert">{{ $message }}</p>
    @enderror

    <button
        class="mx-auto mt-8 flex h-11 w-full cursor-pointer items-center justify-center rounded-[30px] text-base transition-[background-color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink disabled:cursor-wait disabled:opacity-60 lg:h-[34px] lg:w-[400px] {{ $submitClass() }}"
        type="submit"
        x-bind:disabled="sending"
    >{{ $submitLabel() }}</button>
</form>
