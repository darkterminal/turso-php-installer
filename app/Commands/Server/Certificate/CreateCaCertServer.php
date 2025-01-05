<?php

namespace App\Commands\Server\Certificate;

use App\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class CreateCaCertServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:ca-cert-create {name=ca}
        {--expiry=30 : Expiry in days, default is 30 days}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate CA certificate';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        if (!$server->checkRequirement()) {
            $this->error(' 🚫 The required Python packages are not installed. Please install them and try again.');
            return;
        }

        if ($server->createCaCert($this->argument('name'), $this->option('expiry'))) {
            $this->info('  ✨ CA certificate created and store at ' . get_global_metadata('cert_store_location'));
            return;
        }
        $this->error("  🙈 CA certificate creation failed");
    }
}
