<?php

namespace Turso\PHP\Installer\Commands\Sqld\Runner;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class KillDaemonRunning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:daemon-kill {daemon-pid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kill a running sqld daemon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }
        
        $daemon_pid = $this->argument('daemon-pid');
        $daemon_file = get_plain_installation_dir() . DS . 'sqld-daemon-lists.json';
        $daemons = json_decode(file_get_contents($daemon_file), true);
        
        if (empty($daemons)) {
            $this->comment('No running sqld daemons found');
            exit;
        }
        
        foreach ($daemons as $index => $daemon) {
            if ($daemon['pid'] == $daemon_pid) {
                $this->comment("Killing sqld daemon with pid $daemon_pid");
                $process = Process::run("kill -9 $daemon_pid");
                if ($process->successful()) {
                    unset($daemons[$index]);
                    file_put_contents($daemon_file, json_encode($daemons, JSON_PRETTY_PRINT));
                }
                break;
            }
        }
    }
}
