<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Database\Seeders\ItemsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 商品モデルの型の変換と、商品の初期データ（ItemsTableSeeder）のテスト
 */
class ItemModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * recommend・is_reserved_only は、DBから読んだときも真偽値になる（1・0 の整数のままにならない）
     */
    #[Test]
    public function おすすめと予約限定は真偽値として読み込まれる(): void
    {
        $id = Item::factory()->create(['recommend' => true, 'is_reserved_only' => false])->id;

        $item = Item::query()->findOrFail($id);

        $this->assertTrue($item->recommend);
        $this->assertFalse($item->is_reserved_only);

        $item->update(['recommend' => false, 'is_reserved_only' => true, 'reservation_limit' => 10]);
        $item = Item::query()->findOrFail($id);

        $this->assertFalse($item->recommend);
        $this->assertTrue($item->is_reserved_only);
        $this->assertSame(1, $item->getRawOriginal('is_reserved_only'));
    }

    /**
     * 会員の casts は、使っていない email_verified_at を含まず、パスワードのハッシュ化は残る
     */
    #[Test]
    public function 会員の型の変換にメール確認日時は含まれない(): void
    {
        $casts = (new User)->getCasts();

        $this->assertArrayNotHasKey('email_verified_at', $casts);
        $this->assertSame('hashed', $casts['password']);
    }

    /**
     * 北の恵みセットの紹介文に、じゃがいも・玉ねぎ・人参の内訳が入る
     */
    #[Test]
    public function 北の恵みセットの紹介文に内訳が入る(): void
    {
        $this->seed(ItemsTableSeeder::class);

        $five = Item::query()->where('name', '北の恵みセット')->where('weight', 5)->firstOrFail();
        $ten = Item::query()->where('name', '北の恵みセット')->where('weight', 10)->firstOrFail();

        $this->assertSame('甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです（じゃがいも2kg・玉ねぎ2kg・人参1kg）', $five->detail);
        $this->assertSame('甘み豊かなにんじん、玉ねぎ、じゃがいもの詰め合わせです（じゃがいも4kg・玉ねぎ4kg・人参2kg）', $ten->detail);
    }
}
