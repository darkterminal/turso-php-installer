<?php

namespace App\Commands;

use App\Repositories\DatabaseTokenGenerator;
use App\Repositories\Installer;
use LaravelZero\Framework\Commands\Command;

class CreateDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:create {db-name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create libSQL Server Database token for Local Development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!(new Installer())->checkIsAlreadyExists()) {
            $this->error("Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }
        $this->comment("Creating libSQL Server Database token for Local Development...");
        $dbName = $this->argument('db-name');
        $this->info((new DatabaseTokenGenerator())->generete($dbName)->toJSON(true));
    }
}
