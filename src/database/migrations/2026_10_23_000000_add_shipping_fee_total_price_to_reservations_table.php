<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 送料・合計金額を追加する（予約は先払いのため代引手数料は持たない）。
     * 既存の行は0で埋め、そのあとデフォルト値を外す（新しい行は必ず値を指定する）。
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('shipping_fee')->default(0)->after('payment_method');
            $table->integer('total_price')->default(0)->after('shipping_fee');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('shipping_fee')->change();
            $table->integer('total_price')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee', 'total_price']);
        });
    }
};
