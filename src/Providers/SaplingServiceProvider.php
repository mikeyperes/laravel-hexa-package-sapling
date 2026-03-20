<?php

namespace hexa_package_sapling\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_package_sapling\Services\SaplingService;

/**
 * SaplingServiceProvider — registers Sapling package services, routes, views.
 */
class SaplingServiceProvider extends ServiceProvider
{
    /**
     * Register services into the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sapling.php', 'sapling');
        $this->app->singleton(SaplingService::class);
    }

    /**
     * Bootstrap package resources.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/sapling.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'sapling');
        $this->registerSidebarItems();
    }

    /**
     * Push sidebar menu items into core layout stacks.
     *
     * @return void
     */
    private function registerSidebarItems(): void
    {
        view()->composer('layouts.app', function ($view) {
            if (config('hexa.app_controls_sidebar', false)) return;
            $view->getFactory()->startPush('sidebar-menu', view('sapling::partials.sidebar-menu')->render());
        });
    }
}
