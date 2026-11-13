<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 資材テーブルを作成する
     */
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedInteger('stock');
            $table->string('location', 100);
            $table->unsignedInteger('threshold');
            $table->timestamps();
        });
    }

    /**
     * 資材テーブルを削除する
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
