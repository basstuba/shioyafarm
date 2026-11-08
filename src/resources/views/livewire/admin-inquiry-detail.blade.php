{{--
    管理画面のお問い合わせ一覧の詳細モーダル
    - $detail：App\Services\InquiryDetail（開いていないときは null）
    一覧の「詳細」ボタンが open-inquiry-detail を送ると開く。外枠（背景・パネル・「閉じる」・Esc）は <x-admin-detail-modal>、各項目は <x-admin-detail-item>（注文・予約・会員の詳細と共通）。
    項目は問い合わせ日・氏名（姓名を1項目にまとめる）・メールアドレス・問い合わせ内容。問い合わせ内容は改行をそのまま表示し（whitespace-pre-wrap。HTMLの記号はエスケープされる）、長いときは枠の中をスクロールする。
--}}
<div>
    @if ($detail !== null)
        <x-admin-detail-modal title-id="admin-inquiry-detail-title" title="お問い合わせの詳細">
            <dl class="flex flex-col gap-7">
                <x-admin-detail-item label="問い合わせ日">{{ $detail->createdAt?->format('Y/m/d') }}</x-admin-detail-item>
                <x-admin-detail-item label="氏名">{{ $detail->fullName }}</x-admin-detail-item>
                <x-admin-detail-item label="メールアドレス">{{ $detail->email }}</x-admin-detail-item>
                <x-admin-detail-item label="問い合わせ内容">
                    <div class="max-h-[40dvh] overflow-y-auto whitespace-pre-wrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink" tabindex="0" role="region" aria-label="問い合わせ内容">{{ $detail->detail }}</div>
                </x-admin-detail-item>
            </dl>
        </x-admin-detail-modal>
    @endif
</div>
