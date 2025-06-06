<?php

namespace Turso\PHP\Installer\Commands\Server\Certificate;

use Turso\PHP\Installer\Contracts\ServerGenerator;
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
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $isRaw = $this->option('raw');
        $server->showCaCert($isRaw);
    }
}
