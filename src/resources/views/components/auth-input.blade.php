@props(['name', 'label', 'type' => 'text', 'placeholder' => '', 'autocomplete' => 'off', 'value' => null, 'srLabel' => false, 'labelClass' => 'text-base font-medium', 'describedby' => null, 'variant' => 'default', 'inputmode' => null, 'maxlength' => null])

{{--
    認証ページ（ログイン・会員登録）の入力欄。ラベル・input・エラーメッセージをまとめて表示する。
    srLabel を true にすると、ラベルは画面に表示せずスクリーンリーダー用にだけ残す（姓・名のように1つの見出しの下に並べる場合）。
    labelClass でラベルのクラスを変えられる（既定は text-base font-medium）。
    スロット labelExtra を渡すと、ラベルの右に並べて出す（「任意」や補足の文言）。スロット hint を渡すと、ラベルの下の行（入力欄の上）に補足の文言を出す。補足の要素の id は describedby にスペース区切りで渡し、input の aria-describedby につなぐ。
    type が password のときだけ、入力欄の右端の内側に「表示」「非表示」の切り替えボタンを出す（Livewire同梱のAlpine.jsで欄ごとに切り替える。パスワードはサーバーに送らない）。
    初期のHTMLは type="password" のままで、Alpine が起動するまでボタンは隠す（x-cloak。[x-cloak] のCSSは @livewireStyles が出すので、使うレイアウトで読み込むこと）。
    ボタンの名前は「表示」「非表示」だけにする（ラベル名を含めると、ラベルで入力欄を探す操作がボタンにも当たるため）。
    variant は default（公開側。橙系の枠線・フォーカス枠・「表示」の文字色）／neutral（管理画面。グレーの枠線・ink のフォーカス枠・muted の「表示」の文字色。管理画面の入力欄で使い回す）。これ以外の値は default と同じ。ラベル・プレースホルダーの色は共通。
    inputmode・maxlength は input に付ける（確認コードのように、数字のキーボードを出したい・桁数を決めたい欄用。省略すると出力しない）。
    class などの属性は外側の div に付く。
    エラーは $errors から name で拾う。パスワードは再表示しない（type が password のときは value を出さない）。
    old() の値は、配列など文字列にできないもの（改変した送信）のときは表示しない。
--}}
@use('App\Support\OldInput')
@php
    $oldValue = OldInput::scalar($name, $value);
    $isPassword = $type === 'password';
    [$borderClass, $outlineClass, $toggleClass] = $variant === 'neutral'
        ? ['border-line-strong', 'focus-visible:outline-ink', 'text-muted']
        : ['border-primary-dark', 'focus-visible:outline-primary-dark', 'text-primary-text'];
    $describedIds = trim(($describedby ?? '').' '.($errors->has($name) ? $name.'-error' : ''));
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 lg:gap-1.5']) }}>
    @if (isset($labelExtra) && $labelExtra->isNotEmpty())
        <div class="flex flex-wrap items-baseline gap-x-[15px] gap-y-1">
            <label @class([$labelClass, 'sr-only' => $srLabel]) for="{{ $name }}">{{ $label }}</label>
            {{ $labelExtra }}
        </div>
    @else
        <label @class([$labelClass, 'sr-only' => $srLabel]) for="{{ $name }}">{{ $label }}</label>
    @endif
    @if (isset($hint) && $hint->isNotEmpty())
        {{ $hint }}
    @endif
    {{--
        パスワード欄は、フォームを送信するとき（文字のままだとブラウザのパスワード保存が効かず、入力履歴に平文で残ることがある）と、
        「戻る」でページが bfcache から復元されたとき（表示したままの状態で戻ってくる）に伏せ字へ戻す。
        pageshow は初回の読み込みの完了時にも起きるので、persisted（復元）のときだけ戻す（読み込みの途中で「表示」を押した切り替えを打ち消さないため）。
        submit はフォーム（この div の祖先）で起きるので window で受ける。type は Alpine の反映を待たずにその場で戻す
    --}}
    <div class="relative" @if ($isPassword) x-data="{ shown: false }" x-on:submit.window="shown = false; $refs.input.type = 'password'" x-on:pageshow.window="if ($event.persisted) shown = false" @endif>
        <input
            @class([
                'h-11 w-full rounded-[10px] border',
                $borderClass,
                'bg-white px-4 text-base placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2',
                $outlineClass,
                'lg:h-10 lg:px-5',
                'pr-16 lg:pr-16' => $isPassword,
            ])
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($isPassword) x-ref="input" x-bind:type="shown ? 'text' : 'password'" @endif
            @if (! $isPassword) value="{{ $oldValue }}" @endif
            placeholder="{{ $placeholder }}"
            autocomplete="{{ $autocomplete }}"
            @if ($inputmode !== null) inputmode="{{ $inputmode }}" @endif
            @if ($maxlength !== null) maxlength="{{ $maxlength }}" @endif
            @if ($errors->has($name)) aria-invalid="true" @endif
            @if ($describedIds !== '') aria-describedby="{{ $describedIds }}" @endif
        />
        @if ($isPassword)
            {{-- 文言（表示／非表示）で状態を伝えるため aria-pressed は付けない（文言と押下状態が二重になり、読み上げで分かりにくくなる） --}}
            <button
                @class([
                    'absolute inset-y-0 right-0 flex w-16 items-center justify-center rounded-r-[10px] text-base whitespace-nowrap',
                    $toggleClass,
                    'focus-visible:outline-2 focus-visible:-outline-offset-2',
                    $outlineClass,
                ])
                type="button"
                x-cloak
                x-on:click="shown = ! shown"
                aria-controls="{{ $name }}"
            ><span x-text="shown ? '非表示' : '表示'">表示</span></button>
        @endif
    </div>
    @error($name)
        <p class="text-sm text-danger" id="{{ $name }}-error">{{ $message }}</p>
    @enderror
</div>
