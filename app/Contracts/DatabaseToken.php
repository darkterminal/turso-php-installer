<?php

namespace App\Contracts;

interface DatabaseToken
{
    public function setTokenExpiration(int $tokenExpiration): void;
    public function generete(string $dbName): void;
    public function displayTable(): void;
    public function getToken(string $db_name, string $key = null): void;
    public function listAllTokens(): void;
    public function deleteToken(string $db_name): void;
    public function deleteAllTokens(): void;
}
