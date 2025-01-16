<?php

namespace Turso\PHP\Installer\Commands\Token;

use Turso\PHP\Installer\Contracts\DatabaseToken;
use Turso\PHP\Installer\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;

class ShowDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:show {db-name} 
        {--fat : Display only full access token}
        {--roa : Display only read-only access token}
        {--pkp : Display only public key pem}
        {--pkb : Display only public key base64}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show libSQL Server Database token for Local Development';

    /**
     * Execute the console command.
     */
    public function handle(
        Installer $installer,
        DatabaseToken $databaseTokenGenerator
    ) {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error(" 🚫 Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }

        $dbName = $this->argument('db-name');

        if ($this->option('fat')) {
            $this->info($databaseTokenGenerator->getToken($dbName, 'full_access_token'));
            return;
        }

        if ($this->option('roa')) {
            $this->info($databaseTokenGenerator->getToken($dbName, 'read_only_token'));
            return;
        }

        if ($this->option('pkp')) {
            $this->info($databaseTokenGenerator->getToken($dbName, 'public_key_pem'));
            return;
        }

        if ($this->option('pkb')) {
            $this->info($databaseTokenGenerator->getToken($dbName, 'public_key_base64'));
            return;
        }
        $this->info($databaseTokenGenerator->getToken($dbName));
    }
}
