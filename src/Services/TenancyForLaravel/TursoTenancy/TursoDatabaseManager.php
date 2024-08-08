<?php

declare(strict_types=1);

namespace App\TursoTenancy;

use Stancl\Tenancy\Contracts\TenantDatabaseManager;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class TursoDatabaseManager implements TenantDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            return file_put_contents(database_path($tenant->database()->getName()), '');
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        try {
            return unlink(database_path($tenant->database()->getName()));
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function databaseExists(string $name): bool
    {
        return file_exists(database_path($name));
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $database               = database_path($databaseName);

        $baseConfig['url']      = "file:$database";

        return $baseConfig;
    }

    public function setConnection(string $connection): void
    {
        // 
    }
}
