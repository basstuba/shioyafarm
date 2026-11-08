@props(['url', 'field', 'notFoundMessage', 'conflictMessage' => null])

{{--
    管理画面の一覧の、チェックの即時保存の外枠（注文・予約の発送済み、お問い合わせの対応済みで共通）。中に <x-admin-check-box> を置く行をスロットに入れる。
    チェックを押した瞬間に Alpine の save(id, checked) が、JSON（{ id, [field]: checked }）を POST で送る（ページは移動しない）。成功なら true、保存できなかったときは false を返し、案内（role="status"）を出す。
    案内の文言：401・419＝ログイン・ページの期限切れ（再読み込みを促す）、409＝conflictMessage（あるときだけ。注文・予約の取消済み）、404＝notFoundMessage（別の画面で削除された）、それ以外・通信エラー＝「保存できませんでした。もう一度お試しください。」
    - url：更新のURL（route(...)）、field：JSON に入れる項目名（shipped・addressed）
    - notFoundMessage：404 のときの案内、conflictMessage：409 のときの案内（省略すると 409 も「保存できませんでした」）
    class などの属性は外枠の div に付く。
--}}
<div
    {{ $attributes }}
    x-data="{
        message: '',
        messages: {
            expired: @js('ページの有効期限が切れました。ページを再読み込みしてください。'),
            conflict: @js($conflictMessage),
            missing: @js($notFoundMessage),
            failed: @js('保存できませんでした。もう一度お試しください。'),
        },
        async save(id, checked) {
            this.message = '';
            let kind = 'failed';
            try {
                const response = await fetch(@js($url), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    body: JSON.stringify({ id: id, [@js($field)]: checked }),
                });
                if (response.ok) {
                    return true;
                }
                if (response.status === 401 || response.status === 419) {
                    // ログインの期限切れ（401）・ページの期限切れ（419）は、再試行しても直らない
                    kind = 'expired';
                } else if (response.status === 409 && this.messages.conflict) {
                    // 別の画面で取り消された（取消済みは発送済みにできない）
                    kind = 'conflict';
                } else if (response.status === 404) {
                    // 別の画面で物理削除された（再試行しても直らない）
                    kind = 'missing';
                }
            } catch (error) {
                // 通信エラーも、保存できなかった扱いにする
            }
            this.message = this.messages[kind];
            return false;
        },
    }"
>
    {{-- 保存に失敗したときの案内 --}}
    <p class="mb-4 rounded-[10px] border border-line-strong bg-surface px-4 py-3 text-base text-ink" role="status" x-text="message" x-show="message" x-cloak></p>

    {{ $slot }}
</div>
