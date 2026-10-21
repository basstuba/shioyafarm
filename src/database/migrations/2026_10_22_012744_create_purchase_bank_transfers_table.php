<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_history_id')->unique()->constrained('purchase_histories')->cascadeOnDelete();
            $table->string('stripe_payment_intent_id', 255)->unique();
            $table->string('bank_name', 255);
            $table->string('branch_name', 255);
            $table->string('account_type', 25);
            $table->string('account_number', 25);
            $table->string('account_holder_name', 255);
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_bank_transfers');
    }
};
