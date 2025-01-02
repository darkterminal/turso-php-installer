<?php

namespace App\Commands\Server\Certificate;

use App\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class ShowCaCertServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:ca-cert-show
        {--raw : Show raw CA certificate and private key}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show raw CA certificate and private key';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        $isRaw = $this->option('raw');
        $server->showCaCert($isRaw);
    }
}
