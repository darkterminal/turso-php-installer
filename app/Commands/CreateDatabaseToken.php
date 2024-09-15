<?php

namespace App\Commands;

use App\Repositories\DatabaseTokenGenerator;
use LaravelZero\Framework\Commands\Command;

class CreateDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:create';

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
        $this->comment("Creating libSQL Server Database token for Local Development...");
        $this->info((new DatabaseTokenGenerator())->generete()->toJSON(true));
    }
}
