<?php

namespace App\Commands;

use App\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;

class UpdateTursoExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Turso libSQL Extension for PHP';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            info(" Turso libSQL Extension for PHP is not installed. Skipping uninstallation... \n Use the `install` command to install the extension.");
            return;
        }

        info('Updating Turso libSQL Extension for PHP...');
        $installer->update();
        info('  ✨ libSQL Extension for PHP updated');
    }
}
