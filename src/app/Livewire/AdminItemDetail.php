<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminDetailModal;
use App\Services\AdminItemService;
use App\Services\ItemDetail;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面の掲載商品一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-item-detail）で開く。管理者としてログインしている場合だけ表示する。
 * ブラウザに渡る公開プロパティ（Livewire のスナップショット）は ID と開閉の状態だけ。商品の項目は描画のたびに取得する。
 */
class AdminItemDetail extends Component
{
    /** @use WithAdminDetailModal<ItemDetail> */
    use WithAdminDetailModal;

    /**
     * 詳細を開く（存在しない・削除済みの商品は何も開かない）
     */
    #[On('open-item-detail')]
    public function openDetail(AdminItemService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminItemService $service): View
    {
        return view('livewire.admin-item-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
