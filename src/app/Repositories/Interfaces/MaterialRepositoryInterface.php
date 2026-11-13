<?php

namespace App\Repositories\Interfaces;

use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;

/**
 * 資材リポジトリインターフェース
 */
interface MaterialRepositoryInterface
{
    /**
     * 全件を登録した順（古い順）に取得
     *
     * @return Collection<int, Material>
     */
    public function all(): Collection;

    /**
     * 残りわずか（在庫数が基準値を下回った）の資材の件数
     */
    public function countLow(): int;

    /**
     * 資材を保存
     *
     * @param  array{name: string, stock: int, location: string, threshold: int}  $data
     */
    public function create(array $data): Material;

    /**
     * 資材を更新（存在しない資材は更新せず false）
     *
     * @param  array{name: string, stock: int, location: string, threshold: int}  $data
     */
    public function update(int $id, array $data): bool;

    /**
     * 資材を物理削除（存在しない資材は何も変えず false）
     */
    public function delete(int $id): bool;
}
