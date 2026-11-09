<?php

namespace Turso\PHP\Installer\Commands\Sqld\Runner;

use Turso\PHP\Installer\Contracts\Background;
use Turso\PHP\Installer\Contracts\DatabaseToken;
use Turso\PHP\Installer\Contracts\EnvironmentManager;
use Turso\PHP\Installer\Traits\Guards\TokenValidatorTrait;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\table;

class RunSqldServer extends Command
{
    use TokenValidatorTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:server-run {env-id-or-name} {db-name}
        {--d|daemon : Run sqld in daemon mode}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run sqld server based on environment id or name';

    /**
     * Execute the console command.
     */
    public function handle(
        Background $background,
        EnvironmentManager $manager,
        DatabaseToken $token
        )
    {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }

        $envIdOrName = $this->argument('env-id-or-name');
        $dbName = $this->argument('db-name');
        $daemon = $this->option('daemon');

        if (!$manager->environmentExists($envIdOrName)) {
            $this->comment(" 🚫 Environment $envIdOrName is not found.\n");
            echo " You need to create environment first, using 'sqld:env-new' command.\n";
            exit;
        }

        $result = $manager->showRawEnvironment($envIdOrName);

        if (!$token->isTokenExists($dbName)) {
            $this->comment(" 🚫 Database $dbName is not found.\n");
            echo " Run 'turso-php-installer token:create <db-name>' first.\n";
            exit;
        }

        $dbToken = $token->getRawToken($dbName, "public_key_pem");

        // the signing key lives with the environment's data (install dir),
        // not in the shared /tmp; the dir is ensured for the database and
        // the daemon logs before anything writes into it
        $environmentDataDirectory = sqld_database_path() . DS . $result['name'];
        if (!is_dir($environmentDataDirectory)) {
            mkdir($environmentDataDirectory, 0755, true);
        }

        $databaseDataDirectory = dirname($result['variables']['SQLD_DB_PATH']);
        if (!is_dir($databaseDataDirectory)) {
            mkdir($databaseDataDirectory, 0755, true);
        }

        $jwtKeyFile = $environmentDataDirectory . DS . "jwt_key.pem";
        file_put_contents($jwtKeyFile, $dbToken);

        $result['variables']['SQLD_AUTH_JWT_KEY_FILE'] = $jwtKeyFile;

        $http_listen_addr = $result['variables']['SQLD_HTTP_LISTEN_ADDR'];

        if (!empty($result['variables']['SQLD_GRPC_LISTEN_ADDR']) && !empty($result['variables']['SQLD_PRIMARY_GRPC_URL'])) {
            $this->error(' 🚫 The environment contains both SQLD_GRPC_LISTEN_ADDR and SQLD_PRIMARY_GRPC_URL - sqld rejects that combination (--grpc-listen-addr vs --primary-grpc-url). Edit the environment (`sqld:env-edit`) or recreate it via `sqld:env-new --variables`.');
            exit(1);
        }

        $auth_token = $token->getRawToken($dbName, 'full_access_token');
        $open_link = clickable_link("https://sqld-studio.vercel.app?name=$dbName&url=http://$http_listen_addr&authToken=$auth_token", 'Open in SQLD Studio');

        if (!$this->isValidToken($auth_token, $dbName)) {
            $this->error(" 🚫 The $dbName is incorrect or token is expired");
            exit;
        }

        $env_var = collect($result['variables'])->map(function ($value, $key) {
            return match($key) {
                'SQLD_NO_WELCOME' => implode('=', [$key, (bool) $value]),
                default => implode('=', [$key, $value]),
            };
        })->implode(' ');

        if ($daemon) {
            $errorLogFile = sqld_database_path() . DS . $result['name'] . DS . "sqld-{$result['name']}-error.log";
            $pid = $background->withCommand("{$env_var} sqld")
                ->withStdoutFile(sqld_database_path() . DS . $result['name'] . DS . "sqld-{$result['name']}.log")
                ->withStderrFile($errorLogFile)
                ->run();

            // verify the forked process is actually alive before claiming
            // success: sqld that receives invalid flags (clap errors, bad
            // address, missing file) dies right after the shell detaches it
            $alive = false;
            for ($attempt = 0; $attempt < 5; $attempt++) {
                usleep(400000);
                if (!empty(shell_exec("kill -0 " . escapeshellarg($pid) . " 2>/dev/null && echo 1"))) {
                    $alive = true;
                    break;
                }
            }

            if (!$alive) {
                $this->error(" 🚫 The sqld daemon for '{$result['name']}' exited right after starting (pid: {$pid}).");
                if (file_exists($errorLogFile) && trim($log = file_get_contents($errorLogFile)) !== '') {
                    $this->line($log);
                } else {
                    $this->comment(" Check 'sqld-{$result['name']}-error.log' under " . dirname($errorLogFile) . " for details.");
                }
                exit(1);
            }

            table(
                ['Environment', 'PID'],
                [
                    [
                        'Environment' => $result['name'],
                        'PID' => $pid
                    ]
                ]
            );

            $daemon_file = get_plain_installation_dir() . DS . 'sqld-daemon-lists.json';
            if (!file_exists($daemon_file)) {
                file_put_contents($daemon_file, '[]');
            }
            $daemon_lists = json_decode(file_get_contents($daemon_file), true);
            $daemon_lists[] = [
                'environment' => $result['name'],
                'pid' => $pid
            ];
            file_put_contents($daemon_file, json_encode($daemon_lists));

            $this->info("  ✨ sqld daemon started\n 🌐 $open_link");
        } else {
            Process::forever()->run("{$env_var} sqld", function (string $type, string $output) use ($open_link) {
                echo $output . PHP_EOL;
                if (str_contains($output, 'SQLite autocheckpoint')) {
                    echo "  ✨ sqld daemon started\n 🌐 $open_link" . PHP_EOL;
                }
            });
        }
    }
}
