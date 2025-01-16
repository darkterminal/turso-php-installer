<?php

namespace Turso\PHP\Installer\Commands\Server;

use Turso\PHP\Installer\Contracts\ServerGenerator;
use LaravelZero\Framework\Commands\Command;

class CheckRequirementServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check server requirement, this will check if python3 pip and cyptography lib are installed';

    /**
     * Execute the console command.
     */
    public function handle(ServerGenerator $server)
    {
        $this->info("  Checking server requirement...");
        if ($server->checkRequirement()) {
            $this->info('  ✨ Server requirement met');
        }
    }
}
