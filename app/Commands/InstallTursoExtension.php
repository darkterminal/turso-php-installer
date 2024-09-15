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
        {--php-version= : Define your chosen PHP Version: 8.0, 8.1, 8.2, or 8.3 default: Your Current PHP Version}
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
        (new Installer())->install($autoConfirm, $specifiedVersion);
    }
}
