{{--
    管理画面の一覧（掲載商品・お知らせ）の削除の確認モーダル。一覧の上に重ねる。props（action・idPrefix・note・titleBefore・titleAfter）の説明は App\View\Components\AdminDeleteConfirm にある。
    Alpine の状態は呼び出し側の x-data（confirming・deleting・deleteId・deleteLabel）が持ち、この部品はそれを読む。呼び出し側は、この部品を x-data の要素の中に置くこと。
    - 確認の中は x-trap でフォーカスを閉じ込め、背景のクリックと「キャンセル」で閉じる（Esc で閉じるのは呼び出し側の x-data の要素）。最初のフォーカスは autofocus の「キャンセル」、閉じたら開く前にフォーカスのあった「削除」へ戻る
    - 送信中の「削除する」は押せなくする（二重送信の防止）が、確認を開くときに解除する（呼び出し側の「削除」で deleting = false にする）
--}}
<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4"
    x-show="confirming"
    x-cloak
    x-trap="confirming"
    x-on:click.self="confirming = false"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="{{ $idPrefix }}-title"
    aria-describedby="{{ $idPrefix }}-note"
>
    <form class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-center text-ink shadow-panel" action="{{ $action }}" method="POST" x-on:submit="deleting = true">
        @csrf
        @method('DELETE')
        <input type="hidden" name="id" x-bind:value="deleteId">
        <p id="{{ $idPrefix }}-title" class="text-base font-bold break-words" x-text="{{ $titleExpression() }}"></p>
        <p id="{{ $idPrefix }}-note" class="mt-3 text-base">{{ $note }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            <x-admin-small-button type="submit" x-bind:disabled="deleting" class="disabled:cursor-wait disabled:opacity-40">削除する</x-admin-small-button>
            <x-admin-small-button variant="cancel" autofocus x-on:click="confirming = false">キャンセル</x-admin-small-button>
        </div>
    </form>
</div>
