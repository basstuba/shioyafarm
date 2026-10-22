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
     * PaymentIntent を作った時点で Stripe の顧客残高から支払い済みになった注文は、振込先の案内（instructions）が無い。
     * その注文でも PaymentIntent ID と金額は保存できるよう、振込先の各項目を null 可にする。
     */
    public function up(): void
    {
        Schema::table('purchase_bank_transfers', function (Blueprint $table) {
            $table->string('bank_name', 255)->nullable()->change();
            $table->string('branch_name', 255)->nullable()->change();
            $table->string('account_type', 25)->nullable()->change();
            $table->string('account_number', 25)->nullable()->change();
            $table->string('account_holder_name', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * 振込先が null の行は空文字に置き換えてから、NOT NULL に戻す。
     */
    public function down(): void
    {
        foreach (['bank_name', 'branch_name', 'account_type', 'account_number', 'account_holder_name'] as $column) {
            DB::table('purchase_bank_transfers')->whereNull($column)->update([$column => '']);
        }

        Schema::table('purchase_bank_transfers', function (Blueprint $table) {
            $table->string('bank_name', 255)->nullable(false)->change();
            $table->string('branch_name', 255)->nullable(false)->change();
            $table->string('account_type', 25)->nullable(false)->change();
            $table->string('account_number', 25)->nullable(false)->change();
            $table->string('account_holder_name', 255)->nullable(false)->change();
        });
    }
};
