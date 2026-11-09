<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 注文した時点の商品名（item_name）と重さ（item_weight）を、予約（reservations）に追加する。
     * 商品を削除・修正しても、過去の注文・予約の表示が変わらないようにするため。
     * 既存の行は、商品の今の値（論理削除済みを含む）で埋めてから NOT NULL にする。
     * （静的解析がテーブル名を読み取れるよう、テーブル名は文字列で直接書く）
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id');
            $table->integer('item_weight')->nullable()->after('item_name');
        });

        foreach (DB::table('items')->get(['id', 'name', 'weight']) as $item) {
            DB::table('reservations')->where('item_id', $item->id)->update(['item_name' => $item->name, 'item_weight' => $item->weight]);
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('item_name')->nullable(false)->change();
            $table->integer('item_weight')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'item_weight']);
        });
    }
};
