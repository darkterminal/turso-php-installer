<?php

namespace Turso\PHP\Installer\Commands\Sqld\Environment;

use Turso\PHP\Installer\Contracts\EnvironmentManager;
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
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $name_or_id = $this->argument('name-or-id');
        $manager->showEnvironment($name_or_id);
    }
}
