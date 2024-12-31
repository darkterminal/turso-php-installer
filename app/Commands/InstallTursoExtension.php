<?php

namespace App\Commands;

use App\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;

class InstallTursoExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install 
        {--unstable : Install the unstable version} 
        {--php-ini= : Specify the php.ini file}
        {--php-version= : Specify the PHP version}
        {--extension-dir= : Specify the PHP extension directory}
        {--non-thread-safe : Install the non-thread-safe version}';

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
            info(" Turso libSQL Extension for PHP is already installed. Skipping installation... \n Use the `update` command to update the extension.");
            return;
        }

        if ($this->option('php-ini')) {
            $installer->setPhpIni($this->option('php-ini'));
        }

        if ($this->option('php-version')) {
            $installer->setPhpVersion($this->option('php-version'));
        }

        if ($this->option('extension-dir')) {
            $installer->setExtensionDir($this->option('extension-dir'));
        }

        if ($this->option('non-thread-safe')) {
            $installer->setNonThreadSafe();
        }

        if ($this->option('unstable')) {
            $installer->setUnstable(true);
        }

        info('Installing libSQL Extension for PHP...');
        $installer->install();
        info('  ✨ libSQL Extension for PHP installed');
    }
}
