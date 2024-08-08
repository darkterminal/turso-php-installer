<?php

declare(strict_types=1);

namespace App\TursoTenancy;

use Illuminate\Support\Facades\App;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use Illuminate\Support\Facades\DB;

class TursoTenancyBootstrapper implements TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant)
    {
        $tenantId = $tenant->getTenantKey();

        $db_prefix = config('tenancy.database.prefix');
        $db_suffix = config('tenancy.database.suffix');
        $db = $db_prefix . $tenantId . $db_suffix;

        if ($this->isRunningMigrations()) {
            config([
                'database.connections.libsql.url' => 'file:' . $db,
            ]);
        } else {
            config([
                'database.connections.libsql.database' => database_path($db),
            ]);
        }

        DB::purge('libsql');
        DB::reconnect('libsql');

        DB::setDefaultConnection('libsql');
    }

    public function revert()
    {
        config([
            'database.connections.libsql.database' => config('database.connections.central.database'),
        ]);

        DB::purge('libsql');
        DB::reconnect('libsql');

        DB::setDefaultConnection(config('database.default'));
    }

    /**
     * Determine if the application is running migrations.
     *
     * @return bool
     */
    protected function isRunningMigrations()
    {
        $commands = [
            'tenants:migrate',
            'tenants:rollback'
        ];
        return App::runningInConsole() && in_array($_SERVER['argv'][1], $commands);
    }
}