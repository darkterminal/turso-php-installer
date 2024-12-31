<?php

namespace App\Commands\Installer;

use App\Contracts\Installer;
use App\Traits\HasInstallQuestions;
use App\Traits\NonInteractive;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class InstallTursoExtension extends Command
{
    use NonInteractive, HasInstallQuestions;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install 
        {--unstable : Install the unstable version from development repository} 
        {--thread-safe : Install the Thread Safe (TS) version}
        {--php-ini= : Specify the php.ini file}
        {--php-version= : Specify the PHP version}
        {--extension-dir= : Specify the PHP extension directory}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Turso libSQL Extension for PHP';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if ($installer->checkIfAlreadyInstalled()) {
            if (!confirm(" Turso libSQL Extension for PHP is already installed. Update the extension?", false)) {
                info(" Skipping installation... \n Use the `update` command to update the extension.");
                return;
            }
        }

        $this->nonInteractive($installer);

        info('Installing libSQL Extension for PHP...');
        
        $this->askQuestions($installer);
        
        info('  ✨ libSQL Extension for PHP installed');
    }
}
