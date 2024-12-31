<?php

namespace App\Commands\Installer;

use App\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;

class UninstallTursoExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uninstall';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uninstall Turso libSQL Extension for PHP';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            info(" Turso libSQL Extension for PHP is not installed. Skipping uninstallation... \n Use the `install` command to install the extension.");
            return;
        }

        info('Uninstalling Turso libSQL Extension for PHP...');
        $installer->uninstall();
        info('  ✨ libSQL Extension for PHP uninstalled');
    }
}
