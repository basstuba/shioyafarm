@extends('layouts.admin.app')

@section('page-title', 'メール作成 | 管理画面 | Shioya Farm')

{{--
    管理画面のメール作成ページ（会員宛て・お問い合わせへの返信。Figma 183:2）。レイアウトは layouts/admin/app（サイドメニューあり。メール作成はメニューに項目がないため太字の項目はない）。
    - $recipient：App\Services\MailRecipient（送信先の種類・ID・氏名・メールアドレス）
    - $back：「一覧へ戻る」と送信後に戻る一覧のURL（来た一覧の検索条件・ページつき。直接開いたときは一覧の1ページ目）
    ページタイトルと「送信する」ボタンは青（mail-link。他の管理画面のタイトルの緑とは違う）。
    宛先のメールアドレスは送らず、宛先の種類（type）とID（id）だけを hidden で送る。サーバーで宛先を引き直す。
    タイトル欄の Enter は確認のモーダルを開く（日本語入力の変換を確定する Enter は何もしない）。
    「送信する」を押すと確認のモーダルを出し、モーダルの「送信する」で送る（キャンセル・背景・Esc では送らずに閉じ、入力は残す）。
    Alpine は Livewire 同梱のものを使う（confirming：確認を開いているか、sending：送信中。送信中は「送信する」を押せなくして二重送信を防ぐ。「戻る」でページが復元されたときは pageshow で両方を戻す）。
    $back：入力エラーなどで戻ったときも失われないよう、作成ページのURLのクエリ（?back=）にも持ち回る。
    PC（lg以上）は幅800pxのカード（内側の余白108px。入力欄の幅はFigmaの400px・本文584px。「送信する」ボタンは幅400pxでカードの中央（左から200px））、SPはカードを画面幅に収め、入力欄・ボタンは全幅。
--}}
@php
    $titleValue = \App\Support\OldInput::scalar('mail_title', '');
    $textValue = \App\Support\OldInput::scalar('mail_body', '');
    $inputClass = 'w-full rounded-[10px] border bg-white px-4 text-base text-ink placeholder:text-line-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink';
    $labelClass = 'text-base font-bold text-ink';
@endphp

@section('content')
    <div class="flex flex-col items-center">
        <h1 class="text-center text-[28px] font-normal text-mail-link lg:text-[32px]">メール作成</h1>

        <form
            class="mt-6 w-full max-w-[800px] rounded-[30px] bg-white px-4 py-8 shadow-card lg:px-27 lg:py-10"
            action="{{ route('admin.mail.send') }}"
            method="POST"
            x-data="{ confirming: false, sending: false }"
            x-on:submit="if (! confirming) { $event.preventDefault(); return }; sending = true"
            x-on:pageshow.window="confirming = false; sending = false"
            novalidate
        >
            @csrf
            <input type="hidden" name="type" value="{{ $recipient->target->type->value }}">
            <input type="hidden" name="id" value="{{ $recipient->target->id }}">
            <input type="hidden" name="back" value="{{ $back }}">

            <dl class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <dt class="{{ $labelClass }}">送信先氏名</dt>
                    <dd class="min-w-0 text-base break-words">{{ $recipient->fullName }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="{{ $labelClass }}">送信先メールアドレス</dt>
                    <dd class="min-w-0 text-base break-all">{{ $recipient->email }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="mail_title">メールタイトル</label>
                <input
                    class="{{ $inputClass }} h-12 lg:h-10 lg:w-[400px] {{ $errors->has('mail_title') ? 'border-danger' : 'border-line-strong' }}"
                    id="mail_title"
                    name="mail_title"
                    type="text"
                    value="{{ $titleValue }}"
                    placeholder="例：お問い合わせありがとうございます。"
                    autocomplete="off"
                    x-on:keydown.enter="if ($event.isComposing || $event.keyCode === 229) { return }; $event.preventDefault(); confirming = true"
                    aria-describedby="mail_title-note{{ $errors->has('mail_title') ? ' mail_title-error' : '' }}"
                    @if ($errors->has('mail_title')) aria-invalid="true" @endif
                />
                <p id="mail_title-note" class="text-base text-muted lg:text-sm">件名の先頭に【しおや農園】が付きます</p>
                @error('mail_title')
                    <p id="mail_title-error" class="text-base text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex flex-col gap-2">
                <label class="{{ $labelClass }}" for="mail_body">メール本文</label>
                <textarea
                    class="{{ $inputClass }} h-[200px] resize-y py-3 lg:h-[169px] {{ $errors->has('mail_body') ? 'border-danger' : 'border-line-strong' }}"
                    id="mail_body"
                    name="mail_body"
                    placeholder="例：お問い合わせありがとうございました。"
                    @if ($errors->has('mail_body')) aria-invalid="true" aria-describedby="mail_body-error" @endif
                >{{ $textValue }}</textarea>
                @error('mail_body')
                    <p id="mail_body-error" class="text-base text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{-- 送信に失敗したときの案内（赤字） --}}
            @error('send')
                <p class="mt-6 text-base text-danger" role="alert">{{ $message }}</p>
            @enderror

            <button
                class="mx-auto mt-8 flex h-12 w-full cursor-pointer items-center justify-center rounded-[30px] bg-mail-link text-base text-white transition-[background-color,box-shadow] duration-200 hover:bg-mail-link-hover hover:shadow-button focus-visible:bg-mail-link-hover focus-visible:shadow-button focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink lg:h-11 lg:w-[400px]"
                type="button"
                aria-haspopup="dialog"
                x-on:click="confirming = true"
            >送信する</button>

            {{-- 送信の確認。会員の削除の確認と同じ部品・閉じ方（キャンセル・背景の押下・Esc）。ボタンは <x-admin-small-button>（SPは高さ44px・文字16px、lg以上は130×30・文字14px） --}}
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
                x-show="confirming"
                x-cloak
                x-trap="confirming"
                x-on:click.self="confirming = false"
                x-on:keydown.escape.window="confirming = false"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="mail-confirm-title"
            >
                <div class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-center text-ink shadow-panel">
                    <p id="mail-confirm-title" class="text-base font-bold">メールを送信します。よろしいですか？</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-4">
                        <x-admin-small-button variant="send" type="submit" disabled x-bind:disabled="sending || ! confirming" class="disabled:cursor-wait disabled:opacity-40">送信する</x-admin-small-button>
                        <x-admin-small-button variant="cancel" autofocus x-on:click="confirming = false">キャンセル</x-admin-small-button>
                    </div>
                </div>
            </div>
        </form>

        <a
            class="mt-6 inline-flex min-h-11 items-center justify-center px-4 text-base text-ink underline hover:no-underline focus-visible:no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            href="{{ $back }}"
        >一覧へ戻る</a>
    </div>
@endsection
