@props(['name', 'label', 'options' => [], 'placeholder' => '選択してください', 'autocomplete' => 'off', 'value' => null, 'labelClass' => 'text-base font-medium'])

{{--
    認証ページ（会員登録）のセレクトボックス（select と三角は x-select）。auth-input と見た目・エラー表示・aria属性をそろえている。
    labelClass でラベルのクラスを変えられる（既定は text-base font-medium。auth-input と同じ）。
    options は選択肢の配列（値と表示を同じ文字列にする）。先頭に value="" の placeholder を置く。
    未選択のときは文字色を placeholder と同じ色にする。class などの属性は外側の div に付く。
    old() の値は、配列など文字列にできないもの（改変した送信）のときは未選択として扱う。
--}}
@php
    $selected = old($name, $value);
    $selected = is_scalar($selected) ? (string) $selected : '';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 lg:gap-1.5']) }}>
    <label class="{{ $labelClass }}" for="{{ $name }}">{{ $label }}</label>
    <x-select
        breakpoint="lg"
        :id="$name"
        :name="$name"
        :autocomplete="$autocomplete"
        required
        :aria-invalid="$errors->has($name) ? 'true' : null"
        :aria-describedby="$errors->has($name) ? $name.'-error' : null"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $option)
            <option class="text-ink" value="{{ $option }}" @selected($selected === (string) $option)>{{ $option }}</option>
        @endforeach
    </x-select>
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
