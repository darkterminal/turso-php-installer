<?php

namespace App\Commands;

use App\Repositories\Installer;
use LaravelZero\Framework\Commands\Command;

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
    public function handle()
    {
        $this->comment('Updating Turso libSQL Extension for PHP...');
        (new Installer())->update();
    }
}
