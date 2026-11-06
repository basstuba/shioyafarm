<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminDetailModal;
use App\Services\AdminPurchaseService;
use App\Services\OrderDetail;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面の注文一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-purchase-detail）で開く。管理者としてログインしている場合だけ表示する。
 */
class AdminPurchaseDetail extends Component
{
    /** @use WithAdminDetailModal<OrderDetail> */
    use WithAdminDetailModal;

    /**
     * 詳細を開く（存在しない注文は何も開かない）
     */
    #[On('open-purchase-detail')]
    public function openDetail(AdminPurchaseService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminPurchaseService $service): View
    {
        return view('livewire.admin-purchase-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
