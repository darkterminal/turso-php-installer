<?php

namespace Turso\PHP\Installer\Commands\Sqld\Runner;

use Turso\PHP\Installer\Contracts\DatabaseToken;
use Turso\PHP\Installer\Contracts\EnvironmentManager;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Turso\PHP\Installer\Traits\Guards\TokenValidatorTrait;

class OpenDatabaseServer extends Command
{
    use TokenValidatorTrait;
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
            exit(1);
        }

        $envIdOrName = $this->argument('env-id-or-name');
        $dbName = $this->argument('db-name');

        if (!$manager->environmentExists($envIdOrName)) {
            $this->comment(" 🚫 Environment $envIdOrName is not found.\n");
            echo " You need to create environment first, using 'sqld:env-new' command.\n";
            exit(1);
        }

        $result = $manager->showRawEnvironment($envIdOrName);

        if (!$token->isTokenExists($dbName)) {
            $this->comment(" 🚫 Database $dbName is not found.\n");
            echo " Run 'turso-php-installer token:create <db-name>' first.\n";
            exit(1);
        }

        $dbToken = $token->getRawToken($dbName, "public_key_pem");

        // the signing key lives with the environment's data (install dir),
        // not in the shared /tmp
        $dataDirectory = sqld_database_path() . DS . $result['name'];
        if (!is_dir($dataDirectory)) {
            mkdir($dataDirectory, 0755, true);
        }

        $jwtKeyFile = $dataDirectory . DS . "jwt_key.pem";
        file_put_contents($jwtKeyFile, $dbToken);

        $result['variables']['SQLD_AUTH_JWT_KEY_FILE'] = $jwtKeyFile;

        $http_listen_addr = "http://{$result['variables']['SQLD_HTTP_LISTEN_ADDR']}";
        $auth_token = $token->getRawToken($dbName, 'full_access_token');
        
        if (!$this->isValidToken($auth_token, $dbName)) {
            $this->error(" 🚫 The $dbName is incorrect or token is expired");
            exit(1);
        }

        Process::forever()->tty()->run("turso db shell $(echo \"$http_listen_addr\"?auth_token=$auth_token)");
    }
}
