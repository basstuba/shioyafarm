<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithAdminDetailModal;
use App\Services\AdminUserService;
use App\Services\UserDetail;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * 管理画面の会員一覧の詳細モーダル（退会済みの会員は、物理削除の確認もこのビューから行う）
 *
 * 一覧の「詳細」ボタンが送るブラウザのイベント（open-user-detail）で開く。管理者としてログインしている場合だけ表示する。
 * ブラウザに渡る公開プロパティ（Livewire のスナップショット）は ID と開閉の状態だけ。会員の項目は描画のたびに取得し、画面に出す項目だけを持つ UserDetail に詰める。
 * パスワードなどの認証情報は、画面にも Livewire のスナップショットにも渡さない（Service が User のモデルをそのまま渡さない）。
 */
class AdminUserDetail extends Component
{
    /** @use WithAdminDetailModal<UserDetail> */
    use WithAdminDetailModal;

    /**
     * 詳細を開く（存在しない会員は何も開かない）
     */
    #[On('open-user-detail')]
    public function openDetail(AdminUserService $service, int $id): void
    {
        $this->openDetailFor($id, $service->findDetail(...));
    }

    public function render(AdminUserService $service): View
    {
        return view('livewire.admin-user-detail', ['detail' => $this->currentDetail($service->findDetail(...))]);
    }
}
