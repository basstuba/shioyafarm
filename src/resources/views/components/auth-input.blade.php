@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'autocomplete' => 'off', 'value' => null])

{{--
    認証ページ（ログイン・会員登録）の入力欄。ラベル・input・エラーメッセージをまとめて表示する。
    エラーは $errors から name で拾う。パスワードは再表示しない（type が password のときは value を出さない）。
--}}
<div class="flex flex-col gap-2 lg:gap-1.5">
    <label class="text-base font-medium" for="{{ $name }}">{{ $label }}</label>
    <input
        class="h-11 w-full rounded-[10px] border border-primary-dark bg-white px-4 text-base placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-10 lg:px-5"
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    />
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
