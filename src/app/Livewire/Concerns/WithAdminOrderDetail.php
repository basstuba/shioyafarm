<?php

namespace App\Livewire\Concerns;

use App\Services\OrderDetail;
use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * 管理画面の注文一覧・予約一覧の詳細モーダル
 *
 * 管理者としてログインしている場合だけ表示する。詳細の取得（Service）は、各コンポーネントがメソッドインジェクションで受け取り、クロージャで渡す。
 * 閉じる操作は、ビューが $wire.showDetail = false で行う。
 */
trait WithAdminOrderDetail
{
    /** 詳細を開いている注文・予約のID（存在すると確認できたときだけ、openDetailFor が設定する） */
    #[Locked]
    public ?int $detailId = null;

    /** 詳細モーダルを開いているか */
    public bool $showDetail = false;

    /** openDetailFor で取得した詳細（同じリクエストの描画で取得し直さないために持つ。リクエストをまたいでは持たない） */
    protected ?OrderDetail $loadedDetail = null;

    /**
     * 詳細を開く（存在しないIDは何も開かない）
     *
     * @param  Closure(int): ?OrderDetail  $find  詳細の取得
     */
    protected function openDetailFor(int $id, Closure $find): void
    {
        $this->detailId = null;
        $this->showDetail = false;
        $this->loadedDetail = null;

        abort_unless(Auth::guard('admin')->check(), 403);

        $this->loadedDetail = $find($id);

        if ($this->loadedDetail !== null) {
            $this->detailId = $id;
            $this->showDetail = true;
        }
    }

    /**
     * 描画する詳細（開いていないときは null。openDetailFor で取得済みなら、それを使う）
     *
     * @param  Closure(int): ?OrderDetail  $find  詳細の取得
     */
    protected function currentDetail(Closure $find): ?OrderDetail
    {
        if (! $this->showDetail || $this->detailId === null || ! Auth::guard('admin')->check()) {
            return null;
        }

        return $this->loadedDetail ?? $find($this->detailId);
    }
}
