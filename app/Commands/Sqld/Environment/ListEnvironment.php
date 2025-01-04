<?php

namespace App\Commands\Sqld\Environment;

use App\Contracts\EnvironmentManager;
use LaravelZero\Framework\Commands\Command;

class ListEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:env-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all created environments';

    /**
     * Execute the console command.
     */
    public function handle(EnvironmentManager $manager)
    {
        $manager->getEnvironments();
    }
}
