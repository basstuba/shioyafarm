{{-- 支払方法の選択（購入ページ・予約ページ共通）。選択肢は $paymentOptions で渡す --}}
<fieldset class="flex flex-col gap-3">
    <legend class="mb-3 font-maru text-lg md:text-xl">お支払い方法を選択してください</legend>
    <div @class(['grid gap-3 md:gap-4', 'md:grid-cols-3' => count($paymentOptions) >= 3, 'md:grid-cols-2' => count($paymentOptions) < 3])>
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
