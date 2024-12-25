<?php

namespace App\Commands;

use App\Repositories\DatabaseTokenGenerator;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ShowDatabaseToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:show
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
    public function handle()
    {
        if ($this->option('fat')) {
            $this->comment("Your full access token is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken('full_access_token'));
        } elseif ($this->option('roa')) {
            $this->comment("Your read-only access token is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken('read_only_token'));
        } elseif ($this->option('pkp')) {
            $this->comment("Your public key pem is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken('public_key_pem'));
        } elseif ($this->option('pkb')) {
            $this->comment("Your public key base64 is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken('public_key_base64'));
        } else {
            $this->comment("Your database token is: \n");
            $this->info((new DatabaseTokenGenerator())->getToken());
        }
    }
}
