<?php

namespace App\Commands\Token;

use App\Contracts\Installer;
use App\Contracts\DatabaseToken;
use LaravelZero\Framework\Commands\Command;

class DeleteDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:delete {db-name?}
        {--all : Delete all database tokens}
        {--f|force : Force the command to run without confirmation}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a database token';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer, DatabaseToken $tokenGenerator)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error(" 🚫 Turso libSQL Extension for PHP is not installed. Please install it first.");
            return 1;
        }

        $dbName = $this->argument('db-name');
        $all = $this->option('all');
        $force = $this->option('force');

        if ($all) {
            if (!$force && !$this->confirm("Are you sure you want to delete ALL database tokens?", true)) {
                $this->comment("Operation cancelled. No database tokens were deleted.");
                return 0;
            }

            $this->deleteAllTokens($tokenGenerator);
            return 0;
        }

        if (empty($dbName)) {
            $this->error('Please specify the database name or use the --all option to delete all database tokens.');
            return 1;
        }

        if (!$force && !$this->confirmDeletion($dbName)) {
            $this->comment("Operation cancelled. Database token for database: {$dbName} was not deleted.");
            return 0;
        }

        $this->deleteSingleToken($tokenGenerator, $dbName);
        return 0;
    }

    private function deleteAllTokens(DatabaseToken $tokenGenerator): void
    {
        $tokenGenerator->deleteAllTokens();
        $this->comment("All database tokens deleted successfully.");
    }

    private function confirmDeletion(string $dbName): bool
    {
        return $this->confirm("Are you sure you want to delete the database token for database: {$dbName}?", true);
    }

    private function deleteSingleToken(DatabaseToken $tokenGenerator, string $dbName): void
    {
        $tokenGenerator->deleteToken($dbName);
        $this->comment("Database token for database: {$dbName} deleted successfully.");
        $tokenGenerator->listAllTokens();
    }
}
