<?php

namespace Turso\PHP\Installer\Commands\Server\Store;

use LaravelZero\Framework\Commands\Command;

class GetCertStoreServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:cert-store-get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the cert store location';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $this->info(get_global_metadata('cert_store_location'));
    }
}
