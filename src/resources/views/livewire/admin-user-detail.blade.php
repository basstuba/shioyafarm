{{--
    管理画面の会員一覧の詳細モーダルと、退会済みの会員の物理削除の確認
    - $detail：App\Services\UserDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-user-detail を送ると開く。外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>（注文一覧・予約一覧の詳細モーダルと共通）。
    各項目は <x-admin-detail-item>（注文一覧・予約一覧の詳細モーダルと共通）。
    退会済みの会員だけ、退会日と赤い「削除する」を出す。「削除する」は確認のモーダルを開き、確認の「削除する」で DELETE /admin/users/delete へ送る（ページを移る）。
    確認の状態（confirming）と、送信中（deleting）は Alpine が持つ。確認の中は x-trap（Livewire 同梱）でフォーカスを閉じ込め、最初のフォーカスは autofocus の「キャンセル」、閉じたら開く前にフォーカスのあった詳細の「削除する」へ戻る。確認の Esc・背景・「キャンセル」は確認だけを閉じ、詳細は残す。
    送信中の「削除する」は押せなくするが、確認を開くときに解除する（押せないまま固まらないように。管理画面のページは no-store のため、ブラウザの「戻る」では再読み込みされる）。
--}}
<div>
    @if ($detail !== null)
        <x-admin-detail-modal
            title-id="admin-user-detail-title"
            title="会員の詳細"
            state="{ confirming: false, deleting: false }"
            close="confirming = false; $wire.showDetail = false"
            escape="if (confirming) { confirming = false } else { $wire.showDetail = false }"
        >
            <dl class="flex flex-col gap-7">
                <x-admin-detail-item label="姓">{{ $detail->lastName }}</x-admin-detail-item>
                <x-admin-detail-item label="名">{{ $detail->firstName }}</x-admin-detail-item>
                <x-admin-detail-item label="住所">{{ $detail->address }}</x-admin-detail-item>
                <x-admin-detail-item label="電話番号">{{ $detail->tell }}</x-admin-detail-item>
                <x-admin-detail-item label="メールアドレス">{{ $detail->email }}</x-admin-detail-item>
                @if ($detail->withdrawnAt !== null)
                    <x-admin-detail-item label="退会日">{{ $detail->withdrawnAt->format('Y/m/d') }}</x-admin-detail-item>
                @endif
            </dl>

            @if ($detail->withdrawnAt !== null)
                <x-slot:actions>
                    <div class="mt-6 flex justify-center">
                        <x-admin-small-button aria-haspopup="dialog" x-on:click="deleting = false; confirming = true">削除する</x-admin-small-button>
                    </div>
                </x-slot:actions>

                {{-- 物理削除の確認。詳細の上に重ねる。ボタンは <x-admin-small-button>（SPは高さ44px・文字16px、lg以上は130×30・文字14px） --}}
                <x-slot:after>
                    <div
                        class="absolute inset-0 z-10 flex items-center justify-center bg-black/35 p-4"
                        x-show="confirming"
                        x-cloak
                        x-trap="confirming"
                        x-on:click.self="confirming = false"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="admin-user-delete-title"
                        aria-describedby="admin-user-delete-note"
                    >
                        <form class="relative max-h-full w-full max-w-[400px] overflow-y-auto rounded-[30px] bg-white px-6 py-8 text-center text-ink shadow-panel" action="{{ route('admin.users.delete') }}" method="POST" x-on:submit="deleting = true">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $detail->id }}">
                            <p id="admin-user-delete-title" class="text-base font-bold">削除します。よろしいですか？</p>
                            <p id="admin-user-delete-note" class="mt-3 text-base">注文・予約の記録も消えます。必要なら先にエクスポートしてください。</p>
                            <div class="mt-6 flex flex-wrap justify-center gap-4">
                                <x-admin-small-button type="submit" x-bind:disabled="deleting" class="disabled:cursor-wait disabled:opacity-40">削除する</x-admin-small-button>
                                <x-admin-small-button variant="cancel" autofocus x-on:click="confirming = false">キャンセル</x-admin-small-button>
                            </div>
                        </form>
                    </div>
                </x-slot:after>
            @endif
        </x-admin-detail-modal>
    @endif
</div>
