<?php

namespace App\Support;

use Illuminate\Support\ViewErrorBag;

/**
 * 資材一覧の追加・修正のモーダルの初期の状態
 *
 * 一覧を開いたときは、モーダルは閉じていて入力欄は空（在庫数・基準値は0）。
 * 入力の誤り・ページの有効期限切れ（419）で一覧に戻ってきたとき（入力の項目か form にエラーがあるとき）だけ、
 * 送った入力（old）を入れたままモーダルを開く。old に資材のID（id）があれば修正、なければ追加のモーダル。
 * 資材が見つからない案内（errors の material・delete）だけのときは、モーダルは開かない。
 * Blade の x-data（Alpine）の初期値として使う（formOpen は、サイドメニューの body の x-data の open と別の名前にしてある）。
 */
final class MaterialFormState
{
    /** モーダルを開く理由になるエラーの項目 */
    private const array FIELDS = ['name', 'stock', 'location', 'threshold'];

    /**
     * 追加のモーダルの空の入力（在庫数・基準値は0）。一覧の「資材を追加」を押したときの値と、一覧を普通に開いたときの初期値で共通
     *
     * @return array{mode: string, id: string, name: string, stock: string, location: string, threshold: string}
     */
    public static function blank(): array
    {
        return ['mode' => 'create', 'id' => '', 'name' => '', 'stock' => '0', 'location' => '', 'threshold' => '0'];
    }

    /**
     * モーダルの初期の状態
     *
     * @return array{formOpen: bool, mode: string, id: string, name: string, stock: string, location: string, threshold: string, showErrors: bool, invalid: array<string, bool>}
     */
    public static function fromOld(ViewErrorBag $errors): array
    {
        $invalid = [];
        foreach (self::FIELDS as $field) {
            $invalid[$field] = $errors->getBag('default')->has($field);
        }
        $open = in_array(true, $invalid, true) || $errors->getBag('default')->has('form');

        if (! $open) {
            return ['formOpen' => false, ...self::blank(), 'showErrors' => false, 'invalid' => $invalid];
        }

        $id = OldInput::scalar('id');

        return [
            'formOpen' => true,
            'mode' => $id === '' ? 'create' : 'edit',
            'id' => $id,
            'name' => OldInput::scalar('name'),
            'stock' => OldInput::scalar('stock'),
            'location' => OldInput::scalar('location'),
            'threshold' => OldInput::scalar('threshold'),
            'showErrors' => true,
            'invalid' => $invalid,
        ];
    }
}
