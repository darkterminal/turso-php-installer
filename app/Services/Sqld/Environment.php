<?php

namespace App\Services\Sqld;

use App\Contracts\EnvironmentManager;
use App\Handlers\JsonStorage;
use App\ValueObjects\EnvironmentObject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use function Laravel\Prompts\error;
use function Laravel\Prompts\table;

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

        $this->store->delete('id', $environment['id']);
        $this->getEnvironments();
    }

}
