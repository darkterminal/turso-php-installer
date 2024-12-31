<?php

namespace App\Commands\Token;

use App\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;
use App\Services\DatabaseTokenGenerator;

class ListDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all generated database tokens';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error("Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }
        $this->comment("Your database tokens are:");
        (new DatabaseTokenGenerator())->getTokens();
    }
}
