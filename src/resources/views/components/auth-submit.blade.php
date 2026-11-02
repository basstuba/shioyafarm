{{--
    認証画面のフォームの塗りの送信ボタン（ログイン・登録する・再設定メールを送信など）。
    - スロット：ボタンの文言
    - 画面ごとに違う余白・幅は class で足す（例：class="lg:mt-6"）
--}}
<button {{ $attributes->merge(['type' => 'submit'])->class('h-11 w-full cursor-pointer rounded-full bg-primary text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-primary-hover hover:shadow-button focus-visible:bg-primary-hover focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark lg:h-[34px]') }}>{{ $slot }}</button>
