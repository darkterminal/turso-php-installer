<?php

namespace Turso\PHP\Installer\Commands\Server\Certificate;

use Turso\PHP\Installer\Contracts\ServerGenerator;
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
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $server->listCaCert();
    }
}
