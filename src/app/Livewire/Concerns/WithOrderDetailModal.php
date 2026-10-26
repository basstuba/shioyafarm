<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\OrderDetail;
use App\Services\OrderHistoryService;
use App\Support\LoginUser;
use Livewire\Attributes\Locked;

/**
 * 購入履歴・予約履歴の詳細モーダル
 *
 * 詳細は、ログイン中の会員の分だけを取得する。他の会員のIDで開こうとしても、何も開かない。
 * 閉じる操作は、ビューが $wire.showDetail = false で行う。
 */
trait WithOrderDetailModal
{
    /** 詳細を開いている履歴のID（自分の履歴だと確認できたときだけ、openDetail が設定する） */
    #[Locked]
    public ?int $detailId = null;

    /** 詳細モーダルを開いているか */
    public bool $showDetail = false;

    /** openDetail で取得した詳細（同じリクエストの描画で取得し直さないために持つ。リクエストをまたいでは持たない） */
    protected ?OrderDetail $loadedDetail = null;

    /**
     * 履歴の詳細を会員を限定して取得する（他の会員の履歴・存在しない履歴は null）
     */
    abstract protected function findDetail(OrderHistoryService $historyService, User $user, int $id): ?OrderDetail;

    /**
     * 詳細を開く（自分の履歴でなければ何もしない）
     */
    public function openDetail(OrderHistoryService $historyService, int $id): void
    {
        $this->detailId = null;
        $this->showDetail = false;
        $this->loadedDetail = $this->findDetail($historyService, LoginUser::require(), $id);

        if ($this->loadedDetail !== null) {
            $this->detailId = $id;
            $this->showDetail = true;
        }
    }

    /**
     * 描画する詳細（開いていないときは null。openDetail で取得済みなら、それを使う）
     */
    private function currentDetail(OrderHistoryService $historyService, User $user): ?OrderDetail
    {
        if (! $this->showDetail || $this->detailId === null) {
            return null;
        }

        return $this->loadedDetail ?? $this->findDetail($historyService, $user, $this->detailId);
    }
}
