<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 資材（発送用の化粧箱など。在庫数と置き場所を1行で持つ。設計書にない追加）
 *
 * 在庫数が基準値を下回った（在庫数 < 基準値）ものを「残りわずか」とする。基準値が0のときは下回ることがないので「残りわずか」にならない。
 * この判定は isLow()（1件）と low スコープ（件数など。同じ条件）の1か所にまとめ、管理画面トップの件数と一覧の強調で同じ判定を使う。
 */
class Material extends Model
{
    use HasFactory;

    /** 在庫数・基準値の上限 */
    public const int NUMBER_MAX = 99999;

    /** 資材名の文字数の上限 */
    public const int NAME_MAX = 50;

    /** 置き場所の文字数の上限 */
    public const int LOCATION_MAX = 100;

    /** 管理画面で、対象の資材が見つからないとき（入力の誤り・削除済み・存在しない資材）の案内 */
    public const string NOT_FOUND_MESSAGE = '資材が見つかりません。';

    protected $fillable = [
        'name',
        'stock',
        'location',
        'threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'threshold' => 'integer',
        ];
    }

    /**
     * 残りわずか（在庫数が基準値を下回った）かどうか
     */
    public function isLow(): bool
    {
        return $this->stock < $this->threshold;
    }

    /**
     * 残りわずかの資材に絞る（isLow() と同じ条件）
     *
     * @param  Builder<Material>  $query
     */
    #[Scope]
    protected function low(Builder $query): void
    {
        $query->whereColumn('stock', '<', 'threshold');
    }
}
