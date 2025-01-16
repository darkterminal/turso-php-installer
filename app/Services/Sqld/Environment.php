<?php

namespace Turso\PHP\Installer\Services\Sqld;

use Turso\PHP\Installer\Contracts\EnvironmentManager;
use Turso\PHP\Installer\Handlers\JsonStorage;
use Turso\PHP\Installer\Services\DatabaseToken\DatabaseTokenGenerator;
use Turso\PHP\Installer\ValueObjects\EnvironmentObject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class Environment implements EnvironmentManager
{
    private string $env_dirname = 'sqld-environments';
    private string $env_dir_location;
    private JsonStorage $store;
    protected bool $force = false;

    public function __construct()
    {
        if (!check_libsql_installed()) {
            error(" 🚫 Turso libSQL Extension for PHP is not installed. Please install it first.");
            exit;
        }

        $this->env_dir_location = get_plain_installation_dir() . DS . $this->env_dirname;

        if (!is_dir($this->env_dir_location)) {
            mkdir($this->env_dir_location);
        }

        $this->store = new JsonStorage($this->env_dir_location . DS . "environments.json");
    }

    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    public function environmentExists(string $nameOrId): bool
    {
        if ($this->force) {
            return false;
        }

        $environments = $this->store->load();
        return $environments->where(function ($item) use ($nameOrId) {
            return $item['id'] == $nameOrId || $item['name'] == $nameOrId;
        })->isNotEmpty();
    }

    public function createEnvironment(string $name, array $variables): void
    {
        $variables = collect($variables)
            ->mapWithKeys(function ($value, $key) {
                if ($key === 'SQLD_NO_WELCOME') {
                    return [$key => (bool) $value];
                }

                if ($key === 'SQLD_DB_PATH' && !is_absolute_path($value)) {
                    $value = sqld_database_path() . DS . $value;
                }

                return [$key => $value];
            })
            ->toArray();

        if ($this->force) {
            $store = $this->store->load();
            $store = $store->where('name', '!=', $name);
            $this->store->save($store);

            $object = EnvironmentObject::fromArray([
                'id' => uniqid(),
                'name' => Str::of($name)->lower()->slug('_'),
                'variables' => $variables,
                'created_at' => now(config('app.timezone')),
                'updated_at' => now(config('app.timezone')),
            ]);

            $this->store->add($object->toArray());
            return;
        }

        $object = EnvironmentObject::fromArray([
            'id' => uniqid(),
            'name' => Str::of($name)->lower()->slug('_'),
            'variables' => $variables,
            'created_at' => now(config('app.timezone')),
            'updated_at' => now(config('app.timezone')),
        ]);

        $this->store->add($object->toArray());
    }

    public function getEnvironments(): void
    {
        $environments = $this->store->load();
        table(
            ['id', 'name', 'created_at', 'updated_at'],
            $environments->map(function ($item) {
                $created_at = Carbon::parse($item['created_at'])->setTimezone(config('app.timezone'))->ago();
                $updated_at = Carbon::parse($item['updated_at'])->setTimezone(config('app.timezone'))->ago();
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'created_at' => $created_at,
                    'updated_at' => $updated_at,
                ];
            })->toArray()
        );
    }

    public function showRawEnvironment(string $nameOrId): array
    {
        $environment = $this->store->load()
            ->where(function ($item) use ($nameOrId) {
                return $item['id'] == $nameOrId || $item['name'] == $nameOrId;
            })->first();

        if (empty($environment)) {
            throw new \RuntimeException(" 🚫 Environment {$nameOrId} is not found.");
        }

        return $environment;
    }

    public function showEnvironment(string $nameOrId): void
    {
        $environment = $this->store->load()
            ->where(function ($item) use ($nameOrId) {
                return $item['id'] == $nameOrId || $item['name'] == $nameOrId;
            })->first();

        if (empty($environment)) {
            error(" 🚫 Environment {$nameOrId} is not found.");
            return;
        }

        echo " \n  Environment ID: {$environment['id']} \n  Environment Name: {$environment['name']} \n  Environment Variables:\n";
        table(
            ['key', 'value'],
            collect($environment['variables'])->map(function ($value, $key) {
                return [
                    'key' => $key,
                    'value' => $value,
                ];
            })->toArray()
        );
    }

    public function editEnvironment(string $nameOrId): void
    {
        $environment = $this->store->load()
            ->where(function ($item) use ($nameOrId) {
                return $item['id'] == $nameOrId || $item['name'] == $nameOrId;
            })->first();

        if (empty($environment)) {
            error(" 🚫 Environment {$nameOrId} is not found.");
            return;
        }

        $environmentForm = [];
        $formFields = [
            'SQLD_DB_PATH' => [
                'value' => 'SQLD_DB_PATH',
                'label' => 'Database Path',
                'hint' => 'default: ' . sqld_database_path() . DS . $environment['name'] . DS . 'data.sqld',
            ],
            'SQLD_NODE' => [
                'value' => 'SQLD_NODE',
                'label' => 'Select a node',
                'hint' => 'default: ' . $environment['variables']['SQLD_NODE'],
            ],
            'SQLD_HTTP_LISTEN_ADDR' => [
                'value' => 'SQLD_HTTP_LISTEN_ADDR',
                'label' => 'HTTP Listen Address',
                'hint' => 'default: ' . $environment['variables']['SQLD_HTTP_LISTEN_ADDR'],
            ],
            'SQLD_GRPC_LISTEN_ADDR' => [
                'value' => 'SQLD_GRPC_LISTEN_ADDR',
                'label' => 'GRPC Listen Address',
                'hint' => 'default: ' . ($environment['variables']['SQLD_GRPC_LISTEN_ADDR'] ?? 'N/A'),
            ],
            'SQLD_PRIMARY_GRPC_URL' => [
                'value' => 'SQLD_PRIMARY_GRPC_URL',
                'label' => 'Primary gRPC URL',
                'hint' => 'default: ' . ($environment['variables']['SQLD_PRIMARY_GRPC_URL'] ?? 'N/A'),
            ],
            'SQLD_NO_WELCOME' => [
                'value' => 'SQLD_NO_WELCOME',
                'label' => 'No Welcome',
                'hint' => 'default: ' . $environment['variables']['SQLD_NO_WELCOME'] ? 'Yes' : 'No',
            ],
        ];

        foreach ($environment['variables'] as $key => $value) {
            $environmentForm[$key] = match (true) {
                $key === $formFields['SQLD_DB_PATH']['value'] ||
                $key === $formFields['SQLD_HTTP_LISTEN_ADDR']['value'] ||
                $key === $formFields['SQLD_GRPC_LISTEN_ADDR']['value'] ||
                $key === $formFields['SQLD_PRIMARY_GRPC_URL']['value'] => text(
                    label: $formFields[$key]['label'],
                    placeholder: $value,
                    default: $value,
                    required: true,
                    hint: $formFields[$key]['hint']
                ),
                $key === $formFields['SQLD_NODE']['value'] => select(
                    label: $formFields[$key]['label'],
                    options: ['primary', 'replica', 'standalone'],
                    default: $value,
                    hint: $formFields[$key]['hint']
                ),
                $key === $formFields['SQLD_NO_WELCOME']['value'] => confirm(
                    label: $formFields[$key]['label'],
                    default: $value,
                    hint: $formFields[$key]['hint']
                ),
            };

            if (!empty($environment[$key])) {
                continue;
            }
        }

        $environmentForm = collect($environmentForm)
            ->map(fn($value) => is_bool($value) ? (int) $value : $value)
            ->toArray();

        $environment['variables'] = $environmentForm;

        $this->store->update('id', $environment['id'], $environment);

        $this->showEnvironment($environment['id']);
    }

    public function deleteEnvironment(string $nameOrId): void
    {
        $environment = $this->store->load()
            ->where(function ($item) use ($nameOrId) {
                return $item['id'] == $nameOrId || $item['name'] == $nameOrId;
            })->first();

        if (empty($environment)) {
            error(" 🚫 Environment {$nameOrId} is not found.");
            return;
        }

        File::deleteDirectory(sqld_database_path() . DS . $environment['name']);

        $this->store->delete('id', $environment['id']);
        $this->getEnvironments();
    }

}
