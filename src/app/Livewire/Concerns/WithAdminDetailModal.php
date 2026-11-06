<?php

namespace App\Livewire\Concerns;

use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * 管理画面の一覧（注文・予約・会員）の詳細モーダル
 *
 * 管理者としてログインしている場合だけ表示する（管理者の確認はここだけ）。詳細の取得（Service）は、各コンポーネントがメソッドインジェクションで受け取り、クロージャで渡す。
 * 詳細の型は問わない（注文・予約は OrderDetail、会員は UserDetail）。使うコンポーネントが `@use WithAdminDetailModal<OrderDetail>` のように型を指定する。
 * 閉じる操作は、ビューが $wire.showDetail = false で行う。
 *
 * @template TDetail of object
 */
trait WithAdminDetailModal
{
    /** 詳細を開いているレコード（注文・予約・会員）のID（存在すると確認できたときだけ、openDetailFor が設定する） */
    #[Locked]
    public ?int $detailId = null;

    /** 詳細モーダルを開いているか */
    public bool $showDetail = false;

    /**
     * openDetailFor で取得した詳細（同じリクエストの描画で取得し直さないために持つ。リクエストをまたいでは持たない）
     *
     * @var TDetail|null
     */
    protected ?object $loadedDetail = null;

    /**
     * 詳細を開く（存在しないIDは何も開かない）
     *
     * @param  Closure(int): ?TDetail  $find  詳細の取得
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
     * @param  Closure(int): ?TDetail  $find  詳細の取得
     * @return TDetail|null
     */
    protected function currentDetail(Closure $find): ?object
    {
        if (! $this->showDetail || $this->detailId === null || ! Auth::guard('admin')->check()) {
            return null;
        }

        return $this->loadedDetail ?? $find($this->detailId);
    }
}
