<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 取消日時（管理画面の注文の取消）を追加する。取り消されていない注文は null のまま。
     */
    public function up(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->timestamp('canceled_at')->nullable()->after('shipped');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->dropColumn('canceled_at');
        });
    }
};
