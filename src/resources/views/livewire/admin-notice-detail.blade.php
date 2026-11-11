{{--
    管理画面のお知らせ一覧の詳細モーダル
    - $detail：App\Services\NoticeDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-notice-detail を送ると開く。外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>、各項目は <x-admin-detail-item>（注文・予約・会員・お問い合わせの詳細と共通）。
    項目は Figma の項目名のまま、作成日・タイトル・本文の3つ。タイトルは長いと折り返す。本文は改行をそのまま表示し（whitespace-pre-wrap。HTMLの記号はエスケープされる）、長いときは枠の中をスクロールする。
--}}
<div>
    @if ($detail !== null)
        <x-admin-detail-modal title-id="admin-notice-detail-title" title="お知らせの詳細">
            <dl class="flex flex-col gap-7">
                <x-admin-detail-item label="作成日">{{ $detail->createdAt?->format('Y/m/d') }}</x-admin-detail-item>
                <x-admin-detail-item label="タイトル">{{ $detail->title }}</x-admin-detail-item>
                <x-admin-detail-item label="本文">
                    <div class="max-h-[40dvh] overflow-y-auto whitespace-pre-wrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink" tabindex="0" role="region" aria-label="本文">{{ $detail->text }}</div>
                </x-admin-detail-item>
            </dl>
        </x-admin-detail-modal>
    @endif
</div>
