<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminDetailModal;
use App\Services\AdminInquiryService;
use App\Services\InquiryDetail;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面のお問い合わせ一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-inquiry-detail）で開く。管理者としてログインしている場合だけ表示する。
 * ブラウザに渡る公開プロパティ（Livewire のスナップショット）は ID と開閉の状態だけ。お問い合わせの項目は描画のたびに取得する。
 */
class AdminInquiryDetail extends Component
{
    /** @use WithAdminDetailModal<InquiryDetail> */
    use WithAdminDetailModal;

    /**
     * 詳細を開く（存在しないお問い合わせは何も開かない）
     */
    #[On('open-inquiry-detail')]
    public function openDetail(AdminInquiryService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminInquiryService $service): View
    {
        return view('livewire.admin-inquiry-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
