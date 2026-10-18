@props(['name', 'label', 'options' => [], 'placeholder' => '選択してください', 'autocomplete' => 'off', 'value' => null])

{{--
    認証ページ（会員登録）のセレクトボックス。auth-input と見た目・エラー表示・aria属性をそろえている。
    options は選択肢の配列（値と表示を同じ文字列にする）。先頭に value="" の placeholder を置く。
    未選択のときは文字色を placeholder と同じ色にする。class などの属性は外側の div に付く。
    old() の値は、配列など文字列にできないもの（改変した送信）のときは未選択として扱う。
--}}
@php
    $selected = old($name, $value);
    $selected = is_scalar($selected) ? (string) $selected : '';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 lg:gap-1.5']) }}>
    <label class="text-base font-medium" for="{{ $name }}">{{ $label }}</label>
    <div class="relative">
        <select
            class="h-11 w-full cursor-pointer appearance-none rounded-[10px] border border-primary-dark bg-white pr-10 pl-4 text-base text-ink invalid:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-10 lg:pl-5"
            id="{{ $name }}"
            name="{{ $name }}"
            autocomplete="{{ $autocomplete }}"
            required
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >
            <option value="">{{ $placeholder }}</option>
            @foreach ($options as $option)
                <option class="text-ink" value="{{ $option }}" @selected($selected === (string) $option)>{{ $option }}</option>
            @endforeach
        </select>
        <svg class="pointer-events-none absolute top-1/2 right-4 h-3 w-4 -translate-y-1/2 text-primary-dark" viewBox="0 0 16 12" fill="currentColor" aria-hidden="true">
            <path d="M0 0h16L8 12z" />
        </svg>
    </div>
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
