<?php

namespace hexa_package_sapling\Providers;

use Illuminate\Support\ServiceProvider;
use hexa_package_sapling\Services\SaplingService;
use hexa_core\Services\PackageRegistryService;

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

        // Sidebar links — registered via PackageRegistryService with auto permission checks
        if (!config('hexa.app_controls_sidebar', false)) {
            $registry = app(PackageRegistryService::class);
            // HWS-SIDEBAR-MENU-3L-BEGIN
            $registry->registerDomainGroup('Discovery', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 20);
            $registry->registerSectionGroup('Sandbox', 'Discovery', '', 20);
            // HWS-SIDEBAR-MENU-3L-END

            $registry->registerSidebarLink('sapling.index', 'Sapling', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'Sandbox', 'sapling', 85);
        }
    }
}
