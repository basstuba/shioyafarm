@extends('layouts.thanks')

@section('page-title', 'お問い合わせ完了 | Shioya Farm')

@section('body-class', 'bg-cream-100')

@section('content')
    {{-- 左上と右下の角に置く三角形（正方形を45度回した形を角で切り取ったように見せる） --}}
    <div class="relative flex min-h-screen flex-col items-center gap-8 overflow-hidden px-4 pt-[110px] pb-16 text-center md:gap-[61px] md:pt-[147px]">
        <span class="pointer-events-none absolute top-0 left-0 size-53 bg-cream-200 [clip-path:polygon(0_0,100%_0,0_100%)] md:size-106" aria-hidden="true"></span>
        <span class="pointer-events-none absolute right-0 bottom-0 size-53 bg-cream-200 [clip-path:polygon(100%_0,100%_100%,0_100%)] md:size-106" aria-hidden="true"></span>

        <h1 class="relative text-[56px] leading-none font-medium text-peach md:text-[96px] md:leading-[1.5]">Thank you !</h1>
        <p class="relative text-xl md:text-2xl">お問い合わせありがとうございます。</p>
        {{-- lg以上はデザインどおり<br>の位置だけで3行にする（折り返さない）。それより狭いときは画面幅で折り返し、行末に数文字だけ残らないようにする --}}
        <p class="relative w-full max-w-[814px] text-base leading-7 text-pretty md:text-left md:leading-[30px] lg:w-max lg:max-w-none lg:whitespace-nowrap">
            内容を確認のうえ、3営業日以内にご連絡いたします。しばらく経っても返信が届かない場合は、<br class="hidden lg:inline">入力いただいたメールアドレスに誤りがあるか、迷惑メールフォルダに振り分けられている可能性がございます。<br class="hidden lg:inline">その際はお手数ですが、再度フォームよりお問い合わせください。
        </p>
        <a class="relative mt-6 text-base underline underline-offset-4 hover:opacity-80 md:mt-[51px]" href="{{ route('top.index') }}">ホームに戻る</a>
    </div>
@endsection
