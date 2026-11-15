<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Fortifyのルートは標準どおり登録しているため、config/fortify.php の設定が効くことのテスト
 */
class FortifyRoutesConfigTest extends TestCase
{
    /**
     * features から会員登録を外した状態でアプリを作る（ルートはプロバイダーの起動時に登録されるため、その前に設定を変える）
     */
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->beforeBootstrapping(RegisterProviders::class, function (Application $app) {
            $app['config']->set('fortify.features', [Features::resetPasswords()]);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    #[Test]
    public function featuresから会員登録を外すと会員登録のルートが消える(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));
        $this->get('/register')->assertNotFound();

        // 外していない機能（パスワード再設定）は残る
        $this->assertTrue(Route::has('password.request'));
        $this->get('/forgot-password')->assertOk();
    }
}
