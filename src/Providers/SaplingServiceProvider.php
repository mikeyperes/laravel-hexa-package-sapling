<?php

namespace hexa_package_sapling\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_package_sapling\Services\SaplingService;

class SaplingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sapling.php', 'sapling');
        $this->app->singleton(SaplingService::class);
    }

    public function boot(): void {}
}
