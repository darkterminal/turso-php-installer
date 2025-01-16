<?php

namespace Turso\PHP\Installer\Commands\Installer;

use LaravelZero\Framework\Commands\Command;

class VersionTursoExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Turso PHP Installer version';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Turso libSQL Installer (version: ' . config('app.version') . ')');
        if (class_exists('LibSQL')) {
            $this->info((new \LibSQL(':memory:'))->version());
        }
    }
}
