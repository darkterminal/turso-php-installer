<?php

namespace App\Commands;

use App\Contracts\Installer;
use App\Repositories\DatabaseTokenGenerator;
use LaravelZero\Framework\Commands\Command;

class DeleteDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:delete {db-name : The name of the database}
        {--f|force : Force the command to run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a database token';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error("Turso libSQL Extension for PHP is not installed. Please install it first.");
            return;
        }

        $dbName = $this->argument('db-name');
        $force = $this->option('force');

        if (!$force && !$this->confirm("Are you sure you want to delete the database token for database: {$dbName}?", true)) {
            $this->comment("Operation cancelled. Database token for database: {$dbName} was not deleted.");
            return 0;
        }

        (new DatabaseTokenGenerator())->deleteToken($dbName);
        $this->comment("Database token for database: {$dbName} deleted successfully.");
        (new DatabaseTokenGenerator())->getTokens();
        return 0;
    }
}
