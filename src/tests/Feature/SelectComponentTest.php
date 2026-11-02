<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * select コンポーネント（選択欄と下向きの三角）のテスト
 */
class SelectComponentTest extends TestCase
{
    /**
     * スロットの選択肢と渡した属性が select に付き、三角のアイコンが並ぶ
     */
    #[Test]
    public function 選択肢と属性が_selectに付き三角のアイコンが出る(): void
    {
        $html = Blade::render('<x-select id="area" name="area" wire:model="area" required><option value="">選択してください</option><option value="東京都">東京都</option></x-select>');

        $this->assertMatchesRegularExpression('#<select[^>]*id="area"[^>]*name="area"[^>]*>#', $html);
        $this->assertStringContainsString('wire:model="area"', $html);
        $this->assertStringContainsString(' required', $html);
        $this->assertStringContainsString('<option value="東京都">東京都</option>', $html);
        // 標準の矢印は消し、専用の三角を重ねる
        $this->assertStringContainsString('appearance-none', $html);
        $this->assertStringContainsString('M0 0h16L8 12z', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('invalid:text-line-strong', $html);
    }

    /**
     * 既定は md で高さ40px・枠は茶色、error を渡すと枠が赤になる
     */
    #[Test]
    public function 既定はmdで切り替わり_errorで枠が赤になる(): void
    {
        $normal = Blade::render('<x-select name="a"><option>1</option></x-select>');
        $error = Blade::render('<x-select name="a" :error="true"><option>1</option></x-select>');

        $this->assertStringContainsString('md:h-10', $normal);
        $this->assertStringContainsString('border-primary-dark', $normal);
        $this->assertStringNotContainsString('border-danger', $normal);
        $this->assertStringContainsString('border-danger', $error);
        $this->assertStringNotContainsString('border-primary-dark ', $error);
    }

    /**
     * breakpoint="lg" は lg で高さ40px、wrapper-class は包みの div に付く
     */
    #[Test]
    public function lgの切り替えと包みのクラスを指定できる(): void
    {
        $html = Blade::render('<x-select name="a" breakpoint="lg" wrapper-class="lg:w-75"><option>1</option></x-select>');

        $this->assertStringContainsString('lg:h-10', $html);
        $this->assertStringNotContainsString('md:h-10', $html);
        $this->assertMatchesRegularExpression('#<div class="relative lg:w-75">#', $html);
    }

    /**
     * aria-invalid・aria-describedby は、null を渡すと出力されない
     */
    #[Test]
    public function ariaの属性は_nullのとき出力されない(): void
    {
        $none = Blade::render('<x-select name="a" :aria-invalid="null" :aria-describedby="null"><option>1</option></x-select>');
        $with = Blade::render('<x-select name="a" aria-invalid="true" aria-describedby="a-error"><option>1</option></x-select>');

        $this->assertStringNotContainsString('aria-invalid', $none);
        $this->assertStringNotContainsString('aria-describedby', $none);
        $this->assertStringContainsString('aria-invalid="true"', $with);
        $this->assertStringContainsString('aria-describedby="a-error"', $with);
    }
}
