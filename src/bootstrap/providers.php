<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\ViewServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    ViewServiceProvider::class,
    RepositoryServiceProvider::class,
];
