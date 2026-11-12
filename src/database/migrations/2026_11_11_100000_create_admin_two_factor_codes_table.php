<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 管理者の二段階認証（メールのワンタイムコード）のコードを保存するテーブルを作る
     */
    public function up(): void
    {
        Schema::create('admin_two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->unique()->constrained('admins')->cascadeOnDelete();
            $table->string('code', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    /**
     * 管理者の二段階認証のコードのテーブルを削除する
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_two_factor_codes');
    }
};
