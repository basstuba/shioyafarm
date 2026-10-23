<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * purchase_bank_transfers と同じ構成。PaymentIntent を作った時点で Stripe の顧客残高から支払い済みになった予約は
     * 振込先の案内が無いため、振込先の各項目は null 可にする。
     */
    public function up(): void
    {
        Schema::create('reservation_bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained('reservations')->cascadeOnDelete();
            $table->string('stripe_payment_intent_id', 255)->unique();
            $table->string('bank_name', 255)->nullable();
            $table->string('branch_name', 255)->nullable();
            $table->string('account_type', 25)->nullable();
            $table->string('account_number', 25)->nullable();
            $table->string('account_holder_name', 255)->nullable();
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_bank_transfers');
    }
};
