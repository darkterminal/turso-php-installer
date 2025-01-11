<?php

namespace App\Commands\Sqld\Runner;

use App\Contracts\Background;
use App\Contracts\DatabaseToken;
use App\Contracts\EnvironmentManager;
use App\Traits\Guards\TokenValidatorTrait;
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
            echo " You need to create environment first, using 'sqld:env-create' command.\n";
            exit;
        }

        $result = $manager->showRawEnvironment($envIdOrName);

        $dbToken = $token->getRawToken($dbName, "public_key_pem");
        $jwtKeyFile = sys_get_temp_dir() . DS . $dbName . "_jwt_key.pem";
        file_put_contents($jwtKeyFile, $dbToken);

        $result['variables']['SQLD_AUTH_JWT_KEY_FILE'] = $jwtKeyFile;

        $http_listen_addr = $result['variables']['SQLD_HTTP_LISTEN_ADDR'];
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
            $pid = $background->withCommand("{$env_var} sqld")
                ->withStdoutFile(sqld_database_path() . DS . $result['name'] . DS . "sqld-{$result['name']}.log")
                ->withStderrFile(sqld_database_path() . DS . $result['name'] . DS . "sqld-{$result['name']}-error.log")
                ->run();
            table(
                ['Environment', 'PID'],
                [
                    [
                        'Envrionment' => $result['name'],
                        'PDI' => $pid
                    ]
                ]
            );
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
