<?php

namespace App\Commands;

use App\Repositories\DatabaseTokenGenerator;
use App\Repositories\Installer;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ListDatabaseTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all generated database tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!(new Installer())->checkIsAlreadyExists()) {
            $this->error("Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }
        $this->comment("Your database tokens are:");
        (new DatabaseTokenGenerator())->getTokens();
    }
}
