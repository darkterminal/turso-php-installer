<?php

namespace App\Commands;

use App\Repositories\Installer;
use LaravelZero\Framework\Commands\Command;

class InstallTursoExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install 
        {--y|--yes : Skip interactive installation process} 
        {--php-version= : Define your chosen PHP Version: 8.0, 8.1, 8.2, 8.3 default: Your Current PHP Version}
        {--php-ini-file= : Define your PHP INI file location: eg: /etc/php/<version>/cli/php.ini default: /etc/php/<version>/cli/php.ini}
        {--ext-destination= : Define your PHP Extension Destination: eg: /your/custom/extensions/path default: $HOME/.turso-php-installer or %USERPROFILE%\\.turso-php-installer}
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
    public function handle()
    {
        $autoConfirm = $this->option('yes');
        $specifiedVersion = $this->option('php-version');
        $phpIniFile = $this->option('php-ini-file');
        $extDestination = $this->option('ext-destination');

        (new Installer())->install(
            $autoConfirm,
            $specifiedVersion,
            $phpIniFile,
            $extDestination
        );
    }
}
