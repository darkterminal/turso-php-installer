<?php

namespace Turso\PHP\Installer\Commands\Sqld\Runner;

use LaravelZero\Framework\Commands\Command;

class ListRunningDaemon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:daemon-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all running sqld daemons';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }

        $daemon_file = get_plain_installation_dir() . DS . 'sqld-daemon-lists.json';
        if (!file_exists($daemon_file)) {
            $this->comment('No running sqld daemons found');
            exit;
        }

        $daemons = json_decode(file_get_contents($daemon_file), true);

        if (empty($daemons)) {
            $this->comment('No running sqld daemons found');
            exit;
        }

        $this->comment('Running sqld daemons:');
        $this->table(['environment', 'pid'], $daemons);
    }
}
