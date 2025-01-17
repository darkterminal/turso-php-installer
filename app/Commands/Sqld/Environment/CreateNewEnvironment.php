<?php

namespace Turso\PHP\Installer\Commands\Sqld\Environment;

use Turso\PHP\Installer\Contracts\DatabaseToken;
use Turso\PHP\Installer\Contracts\EnvironmentManager;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateNewEnvironment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sqld:env-new {name : The name of the environment}
        {--variables= : The variables of the environment in JSON/DSN format}
        {--force : Overwrite the environment if it already exists}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new sqld environment, save for future use.';

    protected array $env_varibales = [];

    /**
     * Execute the console command.
     */
    public function handle(
        EnvironmentManager $manager,
        DatabaseToken $databaseToken
    ) {
        if (get_os_name() === 'windows') {
            $this->error('Sorry, sqld or libsql server is not supported for Windows. Try using WSL.');
            exit;
        }

        $name = $this->argument('name');
        $variables = $this->option('variables');
        $force = $this->option('force');

        $manager->setForce($force);

        if (!$force && $manager->environmentExists($name)) {
            $this->warn(" 🚫 An environment with the name '$name' already exists. Use the --force option to overwrite it.");
            return 1;
        }

        if (empty($variables) && !$this->option('no-interaction')) {
            $tokenLists = $databaseToken->getAllTokens();
            $token_options = collect($tokenLists)->mapWithKeys(function ($token) {
                return [$token['id'] => $token['db_name']];
            })->toArray();

            if (empty($token_options)) {
                $this->warn(" No database tokens found. Please create a database token first.\n\n Run 'turso-php-installer token:create {$name}' command first.");
                return 1;
            }

            $token_selected = select(
                label: 'Select a database token',
                options: $token_options,
                hint: 'You should select a database token first before creating an environment'
            );
            $this->setVariable('DATABASE_TOKEN_ID', $token_selected);

            $db_path_default_value = sqld_database_path() . DS . $name . DS . 'data.sqld';
            $db_path = text(
                'Database Path',
                $db_path_default_value,
                $db_path_default_value,
                hint: 'default: ' . sqld_database_path() . DS . $name . DS . 'data.sqld'
            );
            $this->setVariable('SQLD_DB_PATH', $db_path);

            $node_default_value = 'primary';
            $node = select(
                'Select a node',
                [
                    'primary' => 'Primary - Act as a Remote Database and Embedded Replica',
                    'replica' => 'Replica - Act as a Database Replica',
                    'standalone' => 'Standalone - Act as Standalone Database without Embedded Replica',
                ],
                $node_default_value,
                hint: "default: Primary - Act as a Remote Database and Embedded Replica"
            );
            $this->setVariable('SQLD_NODE', $node);

            $http_listen_addr_default_value = '127.0.0.1:8080';
            $http_listen_addr = text(
                'HTTP Listen Address & Port',
                $http_listen_addr_default_value,
                $http_listen_addr_default_value,
                hint: 'default: 127.0.0.1:8080'
            );
            $this->setVariable('SQLD_HTTP_LISTEN_ADDR', $http_listen_addr);

            if ($node === 'primary') {
                $grpc_listen_addr_default_value = '127.0.0.1:5001';
                $grpc_listen_addr = text(
                    label: 'Primary GRPC Listen Address & Port',
                    placeholder: '127.0.0.1:5001',
                    default: $grpc_listen_addr_default_value,
                    hint: "This is the GRPC listen address for the primary node to allow database replication."
                );
                $this->setVariable('SQLD_GRPC_LISTEN_ADDR', $grpc_listen_addr);
            }

            if ($node === 'replica') {
                $primary_grpc_listen_addr_default_value = '127.0.0.1:5001';
                $primary_grpc_listen_addr = text(
                    label: 'Primary GRPC Listen Address & Port',
                    placeholder: '127.0.0.1:5001',
                    default: $primary_grpc_listen_addr_default_value,
                    hint: "This is the GRPC listen address for the primary node to allow database replication."
                );
                $this->setVariable('SQLD_PRIMARY_GRPC_URL', $primary_grpc_listen_addr);
            }

            $no_welcome = confirm(
                label: 'Do you want to disable the welcome message?',
                default: false,
                yes: 'Yes',
                no: 'No',
                hint: 'default: No'
            );
            $this->setVariable('SQLD_NO_WELCOME', $no_welcome ? 1 : 0);
        }

        $this->info(" Creating environment '$name'...");

        $variablesArray = !empty($variables) ? $this->parseVariables($variables) : $this->getVariables();

        $manager->createEnvironment($name, $variablesArray);

        $this->info(" Environment '$name' created.");
    }

    public function setVariable(string $name, string $value)
    {
        $this->env_varibales[$name] = $value;
    }

    public function getVariables(): array
    {
        return $this->env_varibales;
    }

    /**
     * Parse variables input as JSON or DSN format.
     *
     * @param string $variables
     * @return array|false
     */
    protected function parseVariables(string $variables)
    {
        // Attempt to decode as JSON
        $jsonDecoded = json_decode($variables, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $jsonDecoded; // It's valid JSON, return the decoded array
        }

        // If not JSON, attempt to parse as DSN
        parse_str($variables, $dsnArray);

        if (!empty($dsnArray)) {
            return $dsnArray; // Valid DSN, return the associative array
        }

        // Neither JSON nor DSN is valid
        return false;
    }
}
