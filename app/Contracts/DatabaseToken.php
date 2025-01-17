<?php

namespace Turso\PHP\Installer\Contracts;

use Illuminate\Support\Collection;

interface DatabaseToken
{
    public function setTokenExpiration(int $tokenExpiration): void;
    public function generete(string $dbName, bool $displayTable = true): void;
    public function displayTable(): void;
    public function getToken(string $db_name, string $key = null): void;
    public function getRawToken(string $db_name, string $key = null): string;
    public function listAllTokens(): void;
    public function getAllTokens(): Collection;
    public function isTokenAlreadyUsedByEnvironment(int $tokenId): bool;
    public function deleteToken(string $db_name): void;
    public function deleteAllTokens(): void;
}
