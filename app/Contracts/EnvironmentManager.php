<?php

namespace App\Contracts;

interface EnvironmentManager
{
    public function setForce(bool $force): void;
    public function environmentExists(string $nameOrId): bool;
    public function createEnvironment(string $name, array $variables): void;
    public function getEnvironments(): void;
    public function showEnvironment(string $nameOrId): void;
    public function deleteEnvironment(string $nameOrId): void;
}
