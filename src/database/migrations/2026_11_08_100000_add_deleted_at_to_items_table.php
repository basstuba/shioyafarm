<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 商品の論理削除の日時（管理画面の掲載商品の削除）を追加する。削除していない商品は null のまま。
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
