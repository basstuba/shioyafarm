<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 商品名と重さの索引を追加する（管理画面の登録・修正で、同じ商品名・重さの商品を探してロックするときに、全件を走査しないため）。
     * 同じ組み合わせは論理削除した商品と共存できるため、一意制約にはしない。
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['name', 'weight']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['name', 'weight']);
        });
    }
};
