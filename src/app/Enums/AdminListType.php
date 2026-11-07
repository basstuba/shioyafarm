<?php

namespace App\Enums;

/**
 * 管理画面の一覧（注文一覧・予約一覧）の種類
 *
 * 一覧の種類ごとの語（注文・予約）・ルート・イベント名・対象のテーブルは、ここを正とする（リクエスト・エクスポート・ビューが参照する）。
 * 値は POST /admin/export の type にも使う。
 */
enum AdminListType: string
{
    /** 注文一覧 */
    case Purchase = 'purchase';

    /** 予約一覧 */
    case Reservation = 'reservation';

    /**
     * 画面・エラーメッセージ・エクスポートの見出しやファイル名の「注文」「予約」の部分
     */
    public function subject(): string
    {
        return match ($this) {
            self::Purchase => '注文',
            self::Reservation => '予約',
        };
    }

    /**
     * 一覧のルート名（入力の誤りのときの戻り先、リセットの戻り先）
     */
    public function listRoute(): string
    {
        return match ($this) {
            self::Purchase => 'admin.purchase.index',
            self::Reservation => 'admin.reservations.index',
        };
    }

    /**
     * 検索のルート名
     */
    public function searchRoute(): string
    {
        return match ($this) {
            self::Purchase => 'admin.purchase.search',
            self::Reservation => 'admin.reservations.search',
        };
    }

    /**
     * 発送済みの更新のルート名
     */
    public function updateRoute(): string
    {
        return match ($this) {
            self::Purchase => 'admin.purchase.update',
            self::Reservation => 'admin.reservations.update',
        };
    }

    /**
     * 取消のルート名
     */
    public function cancelRoute(): string
    {
        return match ($this) {
            self::Purchase => 'admin.purchase.cancel',
            self::Reservation => 'admin.reservations.cancel',
        };
    }

    /**
     * 「詳細」ボタンが送る Livewire のイベント名（詳細のモーダルの #[On(...)] と同じ名前）
     */
    public function detailEvent(): string
    {
        return "open-{$this->value}-detail";
    }

    /**
     * 一覧のデータのテーブル名（発送済みの更新で、IDの存在を確かめる）
     */
    public function table(): string
    {
        return match ($this) {
            self::Purchase => 'purchase_histories',
            self::Reservation => 'reservations',
        };
    }
}
