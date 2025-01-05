<?php

namespace App\Commands\Server\Store;

use App\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class SetCertStoreServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:cert-store-set {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set/overwrite global certificate store, to use by the server later. Default is same as {installation_dir}/certs';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $this->info("  Setting cert store location...");
        $path = $this->argument('path') ?? get_plain_installation_dir() . DS . 'certs';
        $server->setCertStoreLocation($path);
        $this->info('  ✨ Cert store location set');
    }
}
