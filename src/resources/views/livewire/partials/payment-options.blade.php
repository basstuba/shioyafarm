{{--
    支払方法の選択（購入ページ・予約ページ共通）。選択肢は $paymentOptions で渡す。
    ラジオボタンの丸は見せず（opacity-0 の input をカード全体に重ねる。input は残すのでキーボード・読み上げ・Playwright の check() で使える）、カード全体を押して選ぶ。
    PCは180×200のカードを列の中央に並べる（2枚でも3枚でも中央）。SPは縦1列の横長カード（左にアイコン、右に名前と説明）。
    枠は内側の ring にして、選択中に枠が太くなってもカードの中身がずれないようにする
--}}
<fieldset>
    <legend class="w-full font-maru text-xl md:text-center">お支払い方法を選択してください</legend>
    <div class="mt-4 flex flex-col gap-3 md:mt-15 md:flex-row md:justify-center md:gap-6 lg:gap-[99px]">
        @foreach ($paymentOptions as $option)
            <label class="relative flex min-h-20 w-full cursor-pointer items-center gap-4 rounded-[10px] bg-white px-4 py-3 text-left text-primary-text shadow-panel inset-ring-1 inset-ring-primary-dark has-[input:checked]:bg-selected has-[input:checked]:shadow-selected has-[input:checked]:inset-ring-4 has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-primary-dark md:h-50 md:w-45 md:shrink-0 md:flex-col md:items-center md:justify-start md:gap-0 md:p-0 md:pt-[25px] md:text-center">
                <input type="radio" name="payment_method" value="{{ $option['value'] }}" wire:model.live="paymentMethod" @checked($paymentMethod === $option['value']) class="absolute inset-0 z-10 m-0 size-full cursor-pointer opacity-0">
                <img class="size-12 shrink-0 object-contain md:size-15" src="{{ asset('storage/'.$option['icon']) }}" alt="" aria-hidden="true">
                <span class="flex min-w-0 flex-col md:contents">
                    <span class="text-base break-words md:mt-8">{{ $option['label'] }}</span>
                    <span class="text-sm break-words md:mt-[17px] md:text-xs">{{ $option['description'] }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('payment_method')
        <p class="mt-4 text-sm text-danger" role="alert">{{ $message }}</p>
    @enderror
</fieldset>
