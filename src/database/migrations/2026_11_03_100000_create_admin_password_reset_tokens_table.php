<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 管理者のパスワード再設定トークンのテーブルを作る（会員の password_reset_tokens とは別）
     */
    public function up(): void
    {
        Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * 管理者のパスワード再設定トークンのテーブルを削除する
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_password_reset_tokens');
    }
};
