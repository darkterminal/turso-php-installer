<?php

namespace App\Providers;

use App\Contracts\DatabaseToken;
use App\Contracts\Installer;
use App\Contracts\ServerGenerator;
use App\Services\DatabaseToken\DatabaseTokenFactory;
use App\Services\Installation\InstallerFactory;
use App\Services\LibsqlServer\LibsqlServerFactory;
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
        $this->app->singleton(DatabaseToken::class, function ($app) {
            return DatabaseTokenFactory::create();
        });

        $this->app->singleton(Installer::class, function ($app) {
            return InstallerFactory::create();
        });
        
        $this->app->singleton(ServerGenerator::class, function ($app) {
            return LibsqlServerFactory::create();
        });
    }
}
