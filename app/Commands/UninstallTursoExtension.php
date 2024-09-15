<?php

namespace App\Commands;

use App\Repositories\Installer;
use LaravelZero\Framework\Commands\Command;

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
    public function handle()
    {
        $this->comment('Uninstalling Turso libSQL Extension for PHP...');
        (new Installer())->uninstall();
    }
}
