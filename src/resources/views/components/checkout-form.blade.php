{{--
    購入ページ・予約ページの注文フォーム（Livewire コンポーネントの中に置く）。
    数量・住所・支払方法の操作は Livewire、「注文を確定する」「予約を確定する」は通常のフォーム送信（POST）。
    - 送信中はボタンを押せなくする（二重送信の防止。ブラウザの「戻る」で戻ったときは押せる状態に戻す）
    - 数量の欄を離れずにボタンを押すと、欄の change で Livewire の更新リクエストが送られ、フォーム送信と同時に走る。
      更新リクエストが検証エラーのフラッシュ（セッション）を先に消してしまうため、実行中の Livewire の更新が終わるまで送信を待つ
      （change はクリックより前に発生するが、Livewire は5ms溜めてから送るため、少し待ってから実行中の更新を数える。待つのは最大5秒）
    - props: action（送信先URL）
--}}
@props(['action'])
<form
    method="POST"
    action="{{ $action }}"
    novalidate
    x-data="{
        submitting: false,
        pending: 0,
        unhook: null,
        init() {
            this.unhook = Livewire.hook('commit', ({ succeed, fail }) => {
                this.pending++;
                succeed(() => this.pending--);
                fail(() => this.pending--);
            });
        },
        destroy() {
            this.unhook?.();
        },
        async submit(event) {
            event.preventDefault();
            if (this.submitting) return;
            this.submitting = true;
            const form = event.currentTarget;
            await new Promise((resolve) => setTimeout(resolve, 50));
            for (let waited = 0; this.pending > 0 && waited < 5000; waited += 20) {
                await new Promise((resolve) => setTimeout(resolve, 20));
            }
            HTMLFormElement.prototype.submit.call(form);
        },
    }"
    x-on:submit="submit($event)"
    x-on:pageshow.window="submitting = false"
    {{ $attributes }}
>
    @csrf
    {{ $slot }}
</form>
