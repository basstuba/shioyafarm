<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 注文明細の item_id の外部キーを、cascadeOnDelete から restrictOnDelete に変える。
     * phpMyAdmin などで商品を物理削除しても、注文明細の履歴が一緒に消えないようにするため（商品の削除は論理削除）。
     * 商品に注文・予約が紐づいていると、物理削除は拒否される。
     */
    public function up(): void
    {
        Schema::table('purchase_history_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_history_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });
    }
};
