{{--
    管理画面のお知らせの作成・修正の共通フォーム（Figma お知らせ作成 172:2・notice-create-button 172:24）。
    props（mode・notice）の説明と、入力欄の初期値（old() の値を優先）・クラスの組み立ては App\View\Components\AdminNoticeForm にある。
    カード（幅800px・角丸30・影。PCの内側の余白108px、SPは px-4）は掲載商品の登録・修正（admin-item-form）と同じ形。入力欄の幅はFigmaの値（タイトル400・内容584×169）、SPは全幅。
    Alpine は Livewire 同梱のものを使う。
    - sending：送信中はボタンを押せなくして二重送信を防ぐ（「戻る」でページが復元されたときは pageshow で戻す）
    確認のモーダルは出さない（掲載商品の登録・修正と同じ）。
--}}
<form
    class="mt-6 w-full max-w-[800px] rounded-[30px] bg-white px-4 py-8 shadow-card lg:px-27 lg:py-10"
    action="{{ $action() }}"
    method="POST"
    novalidate
    x-data="{ sending: false }"
    x-on:submit="if (sending) { $event.preventDefault(); return }; sending = true"
    x-on:pageshow.window="sending = false"
>
    @csrf
    @if ($isEdit())
        <input type="hidden" name="id" value="{{ $notice->id }}">
    @endif

    <div class="flex flex-col gap-7.5">
        {{-- お知らせタイトル --}}
        <div class="flex flex-col gap-2">
            <label class="{{ $labelClass() }}" for="title">お知らせタイトル</label>
            <input
                class="{{ $inputClass() }} h-11 w-full lg:h-10 lg:w-[400px] {{ $borderClass($errors->has('title')) }}"
                id="title"
                name="title"
                type="text"
                value="{{ $title }}"
                placeholder="例：お知らせがあります！"
                autocomplete="off"
                @if ($errors->has('title')) aria-invalid="true" aria-describedby="title-error" @endif
            />
            @error('title')
                <p id="title-error" class="{{ $errorClass() }}">{{ $message }}</p>
            @enderror
        </div>

        {{-- お知らせ内容 --}}
        <div class="flex flex-col gap-2">
            <label class="{{ $labelClass() }}" for="text">お知らせ内容</label>
            <textarea
                class="{{ $inputClass() }} h-[169px] w-full resize-y py-3 lg:w-[584px] {{ $borderClass($errors->has('text')) }}"
                id="text"
                name="text"
                placeholder="例：ECサイトで直接お買い求めいただけるようになりました！"
                @if ($errors->has('text')) aria-invalid="true" aria-describedby="text-error" @endif
            >{{ $text }}</textarea>
            @error('text')
                <p id="text-error" class="{{ $errorClass() }}">{{ $message }}</p>
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
