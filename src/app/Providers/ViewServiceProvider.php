<?php

namespace App\Providers;

use App\View\Composers\HeaderComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Override;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.header', HeaderComposer::class);
    }
}
