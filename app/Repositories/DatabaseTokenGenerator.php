<?php

namespace App\Repositories;

use DateTimeImmutable;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

use RuntimeException;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

final class DatabaseTokenGenerator
{
    public int $tokenExpiration = 7;

    protected ?string $privateKey = null;
    protected ?string $publicKeyPem = null;
    protected ?string $pubKeyBase64 = null;
    protected ?InMemory $key = null;
    protected ?string $tempDir = null;
    protected string $home;
    protected string|\LibSQL $tokenStore;

    protected array $results = [];

    /**
     * Initializes the DatabaseTokenGenerator instance by generating an Ed25519 key pair and encoding the public key.
     *
     * @return void
     */
    public function __construct()
    {
        $requiredExtensions = ['openssl', 'sodium'];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new RuntimeException("Error: PHP extension '$ext' is not installed or enabled.");
            }
        }

        $opensslPath = stripos(PHP_OS, 'WIN') === 0 ? shell_exec('where openssl') : shell_exec('which openssl');

        if (!$opensslPath) {
            throw new RuntimeException("Error: OpenSSL command-line tool is not installed or not in your PATH.");
        }

        $this->tempDir = sys_get_temp_dir();
        $this->home = stripos(PHP_OS, 'WIN') === 0 ? getenv('USERPROFILE') : getenv('HOME');

        $this->home = trim($this->home);

        if (!is_dir("{$this->home}" . DIRECTORY_SEPARATOR . ".tpi-metadata")) {
            mkdir("{$this->home}". DIRECTORY_SEPARATOR .".tpi-metadata");
        }

        if ($this->checklibSQLAvailability()) {
            $databaseFileName = "{$this->home}" . DIRECTORY_SEPARATOR . ".tpi-metadata" . DIRECTORY_SEPARATOR . "tokens.db";
            $this->tokenStore = new \LibSQL($databaseFileName);
            $this->tokenStore->execute(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'create_token_table.sql'));
        } else {
            $jsonTokenStore = "{$this->home}" . DIRECTORY_SEPARATOR . ".tpi-metadata" . DIRECTORY_SEPARATOR . "tokens.json";
            if (!file_exists($jsonTokenStore)) {
                touch($jsonTokenStore);
            }

            $this->tokenStore = $jsonTokenStore;
        }

        $this->generatePublicAndPrivateKey();
    }

    public function checklibSQLAvailability(): string|false
    {
        if ($this->checkIsWindows()) {
            $searchLibsql = shell_exec('php -m | findstr libsql');
            return $searchLibsql ? trim($searchLibsql) : false;
        } else {
            $searchLibsql = shell_exec('php -m | grep libsql');
            return $searchLibsql ? trim($searchLibsql) : false;
        }
    }

    private function checkIsWindows(): bool
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';
        return $isWindows;
    }

    /**
     * Generates an Ed25519 key pair and sets the private and public keys.
     *
     * @return void
     */
    protected function generatePublicAndPrivateKey()
    {
        shell_exec("openssl genpkey -algorithm ed25519 -out {$this->tempDir}/jwt_private.pem");
        shell_exec("openssl pkey -in {$this->tempDir}/jwt_private.pem -outform DER | tail -c 32 > {$this->tempDir}/jwt_private.binary");
        shell_exec("openssl pkey -in {$this->tempDir}/jwt_private.pem -pubout -out {$this->tempDir}/jwt_public.pem");

        $this->privateKey = sodium_crypto_sign_secretkey(
            sodium_crypto_sign_seed_keypair(
                file_get_contents("{$this->tempDir}/jwt_private.binary")
            )
        );
        unlink("{$this->tempDir}/jwt_private.binary");

        $this->publicKeyPem = trim(file_get_contents("{$this->tempDir}/jwt_public.pem"));
        $this->pubKeyBase64 = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', $this->publicKeyPem);

        $this->key = InMemory::base64Encoded(
            base64_encode($this->privateKey)
        );
    }

    /**
     * Generates database tokens with full access and read-only permissions.
     *
     * The function creates a full access token and a read-only token, both of which are set to expire after a specified number of days.
     * The tokens are generated using a symmetric signer with a private key.
     * The function returns the current instance with the generated tokens stored in the results array.
     *
     * @return self
     */
    public function generete(string $dbName)
    {
        $tokenExpiration = $this->tokenExpiration;
        $fullAccessToken = (new JwtFacade())->issue(
            new Eddsa(),
            $this->key,
            static fn(
            Builder $builder,
            DateTimeImmutable $issuedAt
        ): Builder => $builder
                ->expiresAt($issuedAt->modify("+{$tokenExpiration} days"))
        );

        $readOnlyToken = (new JwtFacade())->issue(
            new Eddsa(),
            $this->key,
            static fn(
            Builder $builder,
            DateTimeImmutable $issuedAt
        ): Builder => $builder
                ->withClaim('a', 'ro')
                ->expiresAt($issuedAt->modify("+{$tokenExpiration} days"))
        );

        // Prepare response data
        $this->results = [
            'db_name' => $dbName,
            'full_access_token' => $fullAccessToken->toString(),
            'read_only_token' => $readOnlyToken->toString(),
            'public_key_pem' => $this->publicKeyPem,
            'public_key_base64' => $this->pubKeyBase64,
        ];

        return $this;
    }

    /**
     * Converts the generated database token to a JSON string.
     *
     * @param bool $pretty_print Whether to pretty-print the JSON output. Defaults to false.
     * @return void
     */
    public function toJSON(bool $pretty_print = false)
    {
        if ($this->tokenStore instanceof \LibSQL) {
            $this->tokenStore->execute(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'create_new_token.sql'), array_values($this->results));
        } else {
            $tokens = json_decode(file_get_contents($this->tokenStore), true);
            $tokens[] = [
                ...$this->results,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($this->tokenStore, json_encode($tokens));
        }

        $results = $pretty_print ? json_encode(
            $this->results,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) : json_encode($this->results);
        
        $message = <<<MSG
[SUCCESS]

Your database token successfully generated, now you can copy-and-paste
+ full_access_token or read_only_token - in your .env file
+ public_key_pem - create a file jwt_public.pem in your desire directory and save this value
And used with Turso DEV CLI, source explantion: https://gist.github.com/darkterminal/c272bf2a572bc5d7378f31cf4aea5f19
-------------------------------------------------------------------------
MSG;
        info($message);
        echo $results . PHP_EOL;
    }

    /**
     * Returns the generated database tokens.
     *
     * @param string|null $key If specified, returns the value of the given key from the token store. Otherwise, returns the entire token store.
     *
     * @return void
     */
    public function getToken(string $db_name, string $key = null)
    {
        if ($this->tokenStore instanceof \LibSQL) {
            $store = $this->tokenStore->query(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'get_database_token.sql'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
            $store = (array) collect($store)->first();
        } else if (file_exists($this->tokenStore)) {
            $store = json_decode(file_get_contents($this->tokenStore), true);
            $store = collect($store)->where('db_name', $db_name)->first();
        } else {
            error("Not found: your're not generated token yet. Run 'turso-php-install token:create' command first");
            return;
        }

        if ($key) {
            echo collect($store)->get($key) . PHP_EOL;
            return;
        }
        echo collect($store)->toJson( JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
    }

    public function getTokens()
    {
        if ($this->tokenStore instanceof \LibSQL) {
            $store = $this->tokenStore->query(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'get_all_database_token.sql'))->fetchArray(\LibSQL::LIBSQL_ASSOC);
        } else if (file_exists($this->tokenStore)) {
            $store = json_decode(file_get_contents($this->tokenStore), true);
        } else {
            error("Not found: your're not generated token yet. Run 'turso-php-install token:create' command first");
            return;
        }
        
        echo table(
            ['db_name', 'created_at', 'updated_at'],
            collect($store)->map(function ($item) {
                return [
                    'db_name' => $item['db_name'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'],
                ];
            })->toArray()
        );
    }

    public function deleteToken(string $db_name)
    {
        if ($this->tokenStore instanceof \LibSQL) {
            $result = $this->tokenStore->query(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'check_database_name.sql'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
            if (empty($result)) {
                error("Not found: your're not generated token yet. Run 'turso-php-install token:create' command first");
                exit;
            }
            $this->tokenStore->execute(file_get_contents(config('database.sql_statements') . DIRECTORY_SEPARATOR . 'delete_database_token.sql'), [$db_name]);
        } else if (file_exists($this->tokenStore)) {
            $tokens = json_decode(file_get_contents($this->tokenStore), true);
            if (!collect($tokens)->where('db_name', $db_name)->first()) {
                error("Not found: your're not generated token yet. Run 'turso-php-install token:create' command first");
                exit;
            }
            $tokens = collect($tokens)->filter(function ($item) use ($db_name) {
                return $item['db_name'] !== $db_name;
            })->toArray();
            file_put_contents($this->tokenStore, json_encode($tokens));
        }
    }

    public function __destruct()
    {
        unlink("{$this->tempDir}/jwt_public.pem");
        unlink("{$this->tempDir}/jwt_private.pem");
        $this->tokenExpiration = 7;
        $this->privateKey = null;
        $this->publicKeyPem = null;
        $this->pubKeyBase64 = null;
        $this->key = null;
        $this->tempDir = null;
        $this->results = [];
    }
}
