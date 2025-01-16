<?php

namespace Turso\PHP\Installer\Providers;

use Turso\PHP\Installer\Contracts\Background;
use Turso\PHP\Installer\Contracts\DatabaseToken;
use Turso\PHP\Installer\Contracts\EnvironmentManager;
use Turso\PHP\Installer\Contracts\Installer;
use Turso\PHP\Installer\Contracts\ServerGenerator;
use Turso\PHP\Installer\Services\Background\BackgroundProcessFactory;
use Turso\PHP\Installer\Services\DatabaseToken\DatabaseTokenFactory;
use Turso\PHP\Installer\Services\Installation\InstallerFactory;
use Turso\PHP\Installer\Services\LibsqlServer\LibsqlServerFactory;
use Turso\PHP\Installer\Services\Sqld\EnvironmentFactory;
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
        $this->app->singleton(EnvironmentManager::class, function ($app) {
            return EnvironmentFactory::create();
        });
        
        $this->app->singleton(Background::class, function ($app) {
            return BackgroundProcessFactory::create();
        });

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
