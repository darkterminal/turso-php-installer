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
            exit(1);
        }

        $daemon_file = get_plain_installation_dir() . DS . 'sqld-daemon-lists.json';
        if (!file_exists($daemon_file)) {
            $this->comment('No running sqld daemons found');
            exit;
        }

        $daemons = json_decode(file_get_contents($daemon_file), true);

        if (empty($daemons) || !is_array($daemons)) {
            $this->comment('No running sqld daemons found');
            exit;
        }

        // self-heal: drop entries whose process is long gone; entries
        // without a usable pid (corrupt or hand-edited file) are skipped
        // instead of crashing the whole listing
        $liveDaemons = [];
        foreach ($daemons as $daemon) {
            if (!is_array($daemon)) {
                continue;
            }

            $pid = (int) ($daemon['pid'] ?? 0);
            if ($pid < 1) {
                continue;
            }

            if (!empty(shell_exec("kill -0 $pid 2>/dev/null && echo 1"))) {
                $liveDaemons[] = $daemon;
            }
        }

        if (count($liveDaemons) !== count($daemons)) {
            file_put_contents($daemon_file, json_encode($liveDaemons, JSON_PRETTY_PRINT));
        }

        if (empty($liveDaemons)) {
            $this->comment('No running sqld daemons found');
            exit;
        }

        $this->comment('Running sqld daemons:');
        $this->table(['environment', 'pid'], $liveDaemons);
    }
}
