<?php

namespace Turso\PHP\Installer\Commands\Sqld\Environment;

use Turso\PHP\Installer\Contracts\EnvironmentManager;
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
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $manager->getEnvironments();
    }
}
