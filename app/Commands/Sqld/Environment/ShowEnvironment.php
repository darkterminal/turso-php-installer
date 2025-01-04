<?php

namespace App\Commands\Sqld\Environment;

use App\Contracts\EnvironmentManager;
use LaravelZero\Framework\Commands\Command;

class ShowEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:env-show {name-or-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detail of environment';

    /**
     * Execute the console command.
     */
    public function handle(EnvironmentManager $manager)
    {
        $name_or_id = $this->argument('name-or-id');
        $manager->showEnvironment($name_or_id);
    }
}
