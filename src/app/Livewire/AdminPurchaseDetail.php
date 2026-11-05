<?php

namespace App\Livewire;

use App\Services\AdminPurchaseService;
use App\Services\OrderDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面の注文一覧の詳細モーダル
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-purchase-detail）で開く。管理者としてログインしている場合だけ表示する。
 * 閉じる操作は、ビューが $wire.showDetail = false で行う。
 */
class AdminPurchaseDetail extends Component
{
    /** 詳細を開いている注文のID（存在する注文だと確認できたときだけ、openDetail が設定する） */
    #[Locked]
    public ?int $detailId = null;

    /** 詳細モーダルを開いているか */
    public bool $showDetail = false;

    /** openDetail で取得した詳細（同じリクエストの描画で取得し直さないために持つ。リクエストをまたいでは持たない） */
    protected ?OrderDetail $loadedDetail = null;

    /**
     * 詳細を開く（存在しない注文は何も開かない）
     */
    #[On('open-purchase-detail')]
    public function openDetail(AdminPurchaseService $service, int $id): void
    {
        $this->detailId = null;
        $this->showDetail = false;
        $this->loadedDetail = null;

        abort_unless(Auth::guard('admin')->check(), 403);

        $this->loadedDetail = $service->findDetail($id);

        if ($this->loadedDetail !== null) {
            $this->detailId = $id;
            $this->showDetail = true;
        }
    }

    public function render(AdminPurchaseService $service): View
    {
        $detail = null;
        if ($this->showDetail && $this->detailId !== null && Auth::guard('admin')->check()) {
            // openDetail で取得済みならそれを使う（同じリクエストで2回取得しない）
            $detail = $this->loadedDetail ?? $service->findDetail($this->detailId);
        }

        return view('livewire.admin-purchase-detail', ['detail' => $detail]);
    }
}
