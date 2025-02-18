<?php

namespace Turso\PHP\Installer\Commands\Token;

use Turso\PHP\Installer\Contracts\Installer;
use Turso\PHP\Installer\Contracts\DatabaseToken;
use LaravelZero\Framework\Commands\Command;

class CreateDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:create {db-name}
        {--expire=7 : The number of days until the token expires, default is 7 days}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create libSQL Server Database token for Local Development';

    /**
     * Execute the console command.
     */
    public function handle(
        Installer $installer,
        DatabaseToken $databaseTokenGenerator
    ) {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error(" 🚫 Turso libSQL Extension for PHP is not installed. Please install it first.");
            return;
        }

        $dbName = $this->argument('db-name');
        $expire = (int) $this->option('expire');
        $databaseTokenGenerator->setTokenExpiration($expire);
        $databaseTokenGenerator->generete($dbName);
    }
}
