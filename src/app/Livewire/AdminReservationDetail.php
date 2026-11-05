<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminOrderDetail;
use App\Services\AdminReservationService;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面の予約一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-reservation-detail）で開く。管理者としてログインしている場合だけ表示する。
 */
class AdminReservationDetail extends Component
{
    use WithAdminOrderDetail;

    /**
     * 詳細を開く（存在しない予約は何も開かない）
     */
    #[On('open-reservation-detail')]
    public function openDetail(AdminReservationService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminReservationService $service): View
    {
        return view('livewire.admin-reservation-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
