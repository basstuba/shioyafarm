<?php

namespace App\Providers;

use App\Repositories\Interfaces\ItemRepositoryInterface;
use App\Repositories\Interfaces\NoticeRepositoryInterface;
use App\Repositories\ItemRepository;
use App\Repositories\NoticeRepository;
use Illuminate\Support\ServiceProvider;
use Override;

class RepositoryServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(ItemRepositoryInterface::class, ItemRepository::class);
        $this->app->bind(NoticeRepositoryInterface::class, NoticeRepository::class);
    }
}
