<?php

namespace App\Commands\Server\Certificate;

use App\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class ListCaCertServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:ca-cert-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all generated CA certificates';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        $server->listCaCert();
    }
}
