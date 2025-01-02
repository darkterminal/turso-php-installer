<?php

namespace App\Commands\Token;

use App\Contracts\Installer;
use App\Contracts\DatabaseToken;
use LaravelZero\Framework\Commands\Command;

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
    public function handle(Installer $installer, DatabaseToken $tokenGenerator)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error(" 🚫 Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }
        $this->comment(" 🗂️ List all generated database tokens:");
        $tokenGenerator->listAllTokens();
    }
}
