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
    protected $signature = 'install';

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
        info('Installing libSQL Extension for PHP...');
        $installer->install();
        info('  ✨ libSQL Extension for PHP installed');
    }
}
