<?php

namespace App\Commands\Server\Certificate;

use App\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class DeleteCaCertServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:ca-cert-delete {name=ca} 
        {--all : Delete all CA certificates from global store location}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a CA certificate from the global store location';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        if (!$server->checkRequirement()) {
            $this->error(' 🚫 The required Python packages are not installed. Please install them and try again.');
            return;
        }
        $this->info(" 🗑️ Deleting CA certificate...");
        $server->deleteCaCert($this->argument('name'), $this->option('all'));
    }
}