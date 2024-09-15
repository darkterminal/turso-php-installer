<?php

namespace App\Commands;

use App\Repositories\Installer;
use LaravelZero\Framework\Commands\Command;

class Version extends Command
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
        $this->info("Turso libSQL Installer (version: " . Installer::VERSION . ")");
        if (class_exists('LibSQL')) {
            $this->info((new \LibSQL(':memory:'))->version());
        }
    }
}
