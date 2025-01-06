<?php

namespace App\Commands\Sqld\Runner;

use App\Contracts\DatabaseToken;
use App\Contracts\EnvironmentManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class OpenDatabaseServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:open-db {env-id-or-name} {db-name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open database using Turso CLI';

    /**
     * Execute the console command.
     */
    public function handle(
        EnvironmentManager $manager,
        DatabaseToken $token
    ) {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }

        $envIdOrName = $this->argument('env-id-or-name');
        $dbName = $this->argument('db-name');

        $result = $manager->showRawEnvironment($envIdOrName);

        $dbToken = $token->getRawToken($dbName, "public_key_pem");
        $jwtKeyFile = sys_get_temp_dir() . DS . $dbName . "_jwt_key.pem";
        file_put_contents($jwtKeyFile, $dbToken);

        $result['variables']['SQLD_AUTH_JWT_KEY_FILE'] = $jwtKeyFile;

        $http_listen_addr = "http://{$result['variables']['SQLD_HTTP_LISTEN_ADDR']}";
        $auth_token = $token->getRawToken($dbName, 'full_access_token');
        Process::forever()->tty()->run("turso db shell $(echo \"$http_listen_addr\"?auth_token=$auth_token)");
    }
}
