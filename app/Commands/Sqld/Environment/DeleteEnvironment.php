<?php

namespace App\Commands\Sqld\Environment;

use App\Contracts\EnvironmentManager;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;

class DeleteEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:env-delete {name-or-id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete an environment by name or ID';

    /**
     * Execute the console command.
     */
    public function handle(EnvironmentManager $manager)
    {
        $name_or_id = $this->argument('name-or-id');

        if (!$manager->environmentExists($name_or_id)) {
            error(" 🚫 Environment {$name_or_id} is not found.");
            return;
        }

        if (confirm("Are you sure you want to delete the environment: {$name_or_id}?", true)) {
            $this->info(' Deleting environment...');
            $manager->deleteEnvironment($name_or_id);
            $this->info(' Environment deleted successfully.');
            return;
        }

        $this->error(' Aborting.');
    }
}
