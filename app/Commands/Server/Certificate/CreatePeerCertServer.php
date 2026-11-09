<?php

namespace Turso\PHP\Installer\Commands\Server\Certificate;

use Turso\PHP\Installer\Contracts\ServerGenerator;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CreatePeerCertServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:ca-peer-cert-create {name=ca}
        {--expiry=30 : Expiry in days, default is 30 days}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a peer certificate';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit(1);
        }
        
        if (!$server->checkRequirement()) {
            $this->error(' ❗ The required Python packages are not installed. Please install them and try again.');
            return 1;
        }

        if ($server->createPeerCert($this->argument('name'), $this->option('expiry'))) {
            $this->info('  ✨ Peer certificate created and store at ' . get_global_metadata('cert_store_location'));
            return;
        }
        $this->error(" 🙈 Peer certificate creation failed");
        return 1;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
