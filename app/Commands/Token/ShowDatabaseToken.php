<?php

namespace App\Commands\Token;

use App\Services\DatabaseTokenGenerator;
use App\Contracts\Installer;
use LaravelZero\Framework\Commands\Command;

class ShowDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:show {db-name} 
        {--fat : Display only full access token}
        {--roa : Display only read-only access token}
        {--pkp : Display only public key pem}
        {--pkb : Display only public key base64}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show libSQL Server Database token for Local Development';

    /**
     * Execute the console command.
     */
    public function handle(Installer $installer)
    {
        if (!$installer->checkIfAlreadyInstalled()) {
            $this->error("Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }

        $dbName = $this->argument('db-name');
        
        if ($this->option('fat')) {
            $this->comment("Your full access token is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken($dbName, 'full_access_token'));
            return;
        } 
        
        if ($this->option('roa')) {
            $this->comment("Your read-only access token is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken($dbName, 'read_only_token'));
            return;
        } 
        
        if ($this->option('pkp')) {
            $this->comment("Your public key pem is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken($dbName, 'public_key_pem'));
            return;
        } 
        
        if ($this->option('pkb')) {
            $this->comment("Your public key base64 is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken($dbName, 'public_key_base64'));
            return;
        }

        $this->comment("Your database token is: \n");
        $this->info((new DatabaseTokenGenerator())->getToken($dbName));
    }
}
