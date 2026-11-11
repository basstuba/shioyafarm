<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminDetailModal;
use App\Services\AdminNoticeService;
use App\Services\NoticeDetail;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面のお知らせ一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-notice-detail）で開く。管理者としてログインしている場合だけ表示する。
 * ブラウザに渡る公開プロパティ（Livewire のスナップショット）は ID と開閉の状態だけ。お知らせの項目は描画のたびに取得する。
 */
class AdminNoticeDetail extends Component
{
    /** @use WithAdminDetailModal<NoticeDetail> */
    use WithAdminDetailModal;

    /**
     * 詳細を開く（存在しないお知らせは何も開かない）
     */
    #[On('open-notice-detail')]
    public function openDetail(AdminNoticeService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminNoticeService $service): View
    {
        return view('livewire.admin-notice-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
