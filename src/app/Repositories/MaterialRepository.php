<?php

namespace App\Repositories;

use App\Models\Material;
use App\Repositories\Interfaces\MaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * 資材リポジトリ
 */
class MaterialRepository implements MaterialRepositoryInterface
{
    #[Override]
    public function all(): Collection
    {
        return Material::query()->orderBy('id')->get();
    }

    #[Override]
    public function countLow(): int
    {
        return Material::query()->low()->count();
    }

    #[Override]
    public function create(array $data): Material
    {
        return Material::create($data);
    }

    /**
     * 行をロックして取得してから更新する（取得と更新の間に行が消えても、更新できたと答えない）
     */
    #[Override]
    public function update(int $id, array $data): bool
    {
        return DB::transaction(function () use ($id, $data): bool {
            $material = Material::query()->whereKey($id)->lockForUpdate()->first();
            if ($material === null) {
                return false;
            }

            $material->update($data);

            return true;
        });
    }

    #[Override]
    public function delete(int $id): bool
    {
        return Material::query()->whereKey($id)->delete() > 0;
    }
}
