<?php

namespace App\Commands\Sqld\Environment;

use App\Contracts\EnvironmentManager;
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
    public function handle(EnvironmentManager $manager)
    {
        $name = $this->argument('name');
        $variables = $this->option('variables');
        $force = $this->option('force');

        $manager->setForce($force);

        if (!$force && $manager->environmentExists($name)) {
            $this->warn(" 🚫 An environment with the name '$name' already exists. Use the --force option to overwrite it.");
            return 1;
        }

        if (empty($variables) && !$this->option('no-interaction')) {
            // Start interactive mode
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
                ['primary', 'replica', 'standalone'],
                $node_default_value,
                hint: "default: $node_default_value"
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
                $allow_replication = confirm(
                    label: 'Did you want to allow replication?',
                    default: false,
                    yes: 'Yes',
                    no: 'No',
                    hint: 'default: No'
                );
                // Allow replication and set the GRPC listen address to 127.0.0.1:5001 this URL is to be used by the replica to connect to the primary
                if ($allow_replication) {
                    $grpc_listen_addr_default_value = '127.0.0.1:5001';
                    $grpc_listen_addr = text(
                        label: 'Primary GRPC Listen Address & Port',
                        placeholder: '127.0.0.1:5001',
                        default: $grpc_listen_addr_default_value,
                        hint: "This is the GRPC listen address for the primary node to allow database replication."
                    );
                    $this->setVariable('SQLD_GRPC_LISTEN_ADDR', $grpc_listen_addr);
                }
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
            $this->setVariable('SQLD_NO_WELCOME', $no_welcome);
        }

        $this->info(" Creating environment '$name'...");

        $variablesArray = !empty($variables) ? $this->parseVariables($variables) : $this->getVariables();

        $env_db_path = collect(explode(DS, $variablesArray['SQLD_DB_PATH']))
            ->slice(0, -1)
            ->implode(DS);

        if (!is_dir($env_db_path)) {
            mkdir($env_db_path);
        }

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
