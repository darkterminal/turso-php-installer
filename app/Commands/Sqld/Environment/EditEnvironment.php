<?php

namespace Turso\PHP\Installer\Commands\Sqld\Environment;

use Turso\PHP\Installer\Contracts\EnvironmentManager;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class EditEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:env-edit {env-id-or-name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Edit an existing environment by ID or name';

    /**
     * Execute the console command.
     */
    public function handle(EnvironmentManager $manager)
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $manager->editEnvironment($this->argument('env-id-or-name'));
    }
}
