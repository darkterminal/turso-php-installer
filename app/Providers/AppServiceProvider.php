<?php

namespace App\Providers;

use App\Contracts\Installer;
use App\Services\Installation\InstallerFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Installer::class, function ($app) {
            return InstallerFactory::create();
        });
    }
}
