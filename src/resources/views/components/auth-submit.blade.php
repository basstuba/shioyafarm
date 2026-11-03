@props(['variant' => 'primary'])
{{--
    認証画面のフォームの塗りの送信ボタン（ログイン・登録する・再設定メールを送信など）。
    - スロット：ボタンの文言
    - variant：primary（既定。橙の地・白い文字）／accent（管理者用ログイン。緑の地・黒い文字。ホバー・フォーカスで濃い緑の地・白い文字）。これ以外の値は primary と同じ
    - 画面ごとに違う余白・幅は class で足す（例：class="lg:mt-6"）
--}}
@php
    $variantClasses = $variant === 'accent'
        ? 'bg-accent text-ink hover:bg-accent-hover hover:text-white focus-visible:bg-accent-hover focus-visible:text-white focus-visible:outline-accent-hover'
        : 'bg-primary text-white hover:bg-primary-hover focus-visible:bg-primary-hover focus-visible:outline-primary-dark';
@endphp
<button {{ $attributes->merge(['type' => 'submit'])->class(['h-11 w-full cursor-pointer rounded-full text-base transition-[background-color,color,box-shadow] duration-200 hover:shadow-button focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2', $variantClasses, 'lg:h-[34px]']) }}>{{ $slot }}</button>
