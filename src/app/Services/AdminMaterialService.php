<?php

namespace App\Services;

use App\Models\Material;
use App\Repositories\Interfaces\MaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * 管理画面の資材サービス（一覧・残りわずかの件数・追加・修正・削除。設計書にない追加）
 */
class AdminMaterialService
{
    public function __construct(
        private readonly MaterialRepositoryInterface $materialRepository,
    ) {}

    /**
     * 資材を登録した順（古い順）に全件取得
     *
     * @return Collection<int, Material>
     */
    public function list(): Collection
    {
        return $this->materialRepository->all();
    }

    /**
     * 残りわずか（在庫数が基準値を下回った）の資材の件数（管理画面トップに出す。一覧の「残りわずか」と同じ判定）
     */
    public function countLow(): int
    {
        return $this->materialRepository->countLow();
    }

    /**
     * 資材を追加する
     *
     * @param  array{name: string, stock: int, location: string, threshold: int}  $input
     *
     * @throws ValidationException 検証のあとに同じ資材名が登録された（同時に送られた）とき
     */
    public function create(array $input): Material
    {
        try {
            return $this->materialRepository->create($input);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateName();
        }
    }

    /**
     * 資材を修正する（存在しない資材は何も変えず false）
     *
     * @param  array{name: string, stock: int, location: string, threshold: int}  $input
     *
     * @throws ValidationException 検証のあとに同じ資材名が登録された（同時に送られた）とき
     */
    public function update(int $id, array $input): bool
    {
        try {
            return $this->materialRepository->update($id, $input);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateName();
        }
    }

    /**
     * 資材を物理削除する（存在しない資材は何も変えず false。資材を参照するテーブルはない）
     */
    public function delete(int $id): bool
    {
        return $this->materialRepository->delete($id);
    }

    /**
     * 資材名の重複の検証エラー（入力の検証と同じ文言）
     */
    private function duplicateName(): ValidationException
    {
        return ValidationException::withMessages(['name' => 'その資材名はすでに登録されています']);
    }
}
