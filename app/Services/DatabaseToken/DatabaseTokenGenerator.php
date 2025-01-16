<?php

namespace Turso\PHP\Installer\Services\DatabaseToken;

use Turso\PHP\Installer\Contracts\DatabaseToken;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Class DatabaseTokenGenerator
 */
final class DatabaseTokenGenerator implements DatabaseToken
{
    /**
     * @var int The default expiration time in days for generated tokens.
     */
    public int $tokenExpiration = 7;

    /**
     * @var string|null The private key used for signing tokens. Set in the constructor.
     */
    protected ?string $privateKey = null;

    /**
     * @var string|null The PEM representation of the public key. Set in the constructor.
     */
    protected ?string $publicKeyPem = null;

    /**
     * @var string|null The base64 encoded public key. Set in the constructor.
     */
    protected ?string $pubKeyBase64 = null;

    /**
     * @var InMemory|null The key used for signing tokens. Set in the constructor.
     */
    protected ?InMemory $key = null;

    /**
     * @var string|null The temporary directory used for storing generated keys. Set in the constructor.
     */
    protected ?string $temp_dir = null;

    /**
     * @var string The path to the user's home directory.
     */
    protected string $home;

    /**
     * @var \LibSQL The instance of the \LibSQL class used for storing and retrieving tokens.
     */
    protected \LibSQL $tokenStore;

    /**
     * @var array The results of the last query executed by this instance.
     */
    protected array $results = [];

    /**
     * Initializes the DatabaseTokenGenerator instance by generating an Ed25519 key pair and encoding the public key.
     *
     * @return void
     */
    public function __construct()
    {
        check_generator_requirements();

        $this->temp_dir = sys_get_temp_dir();

        if (!is_dir(get_plain_installation_dir())) {
            mkdir(get_plain_installation_dir());
        }

        $databaseFileName = get_plain_installation_dir() . DS . "tokens.db";
        $this->tokenStore = new \LibSQL($databaseFileName);
        $this->tokenStore->execute(sql_file('create_token_table'));

        $this->generatePublicAndPrivateKey();
    }

    /**
     * Sets the default expiration time in days for generated tokens.
     *
     * @param int $tokenExpiration The default expiration time in days for generated tokens.
     *
     * @return void
     */
    public function setTokenExpiration(int $tokenExpiration): void
    {
        $this->tokenExpiration = $tokenExpiration;
    }

    /**
     * Generates an Ed25519 key pair and sets the private and public keys.
     *
     * @return void
     */
    protected function generatePublicAndPrivateKey()
    {
        shell_exec("openssl genpkey -algorithm ed25519 -out {$this->temp_dir}/jwt_private.pem");
        shell_exec("openssl pkey -in {$this->temp_dir}/jwt_private.pem -outform DER | tail -c 32 > {$this->temp_dir}/jwt_private.binary");
        shell_exec("openssl pkey -in {$this->temp_dir}/jwt_private.pem -pubout -out {$this->temp_dir}/jwt_public.pem");

        $this->privateKey = sodium_crypto_sign_secretkey(
            sodium_crypto_sign_seed_keypair(
                file_get_contents("{$this->temp_dir}/jwt_private.binary")
            )
        );
        unlink("{$this->temp_dir}/jwt_private.binary");

        $this->publicKeyPem = trim(file_get_contents("{$this->temp_dir}/jwt_public.pem"));
        $this->pubKeyBase64 = str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"], '', $this->publicKeyPem);

        $this->key = InMemory::base64Encoded(
            base64_encode($this->privateKey)
        );
    }

    /**
     * Checks if a token already exists in the token store for the given database name.
     *
     * @param string $dbName The database name to check.
     *
     * @return bool True if the token exists, false otherwise.
     */
    public function isTokenExists(string $dbName): bool
    {
        $exists = $this->tokenStore->query(
            "SELECT * FROM tokens WHERE db_name = ?",
            [$dbName]
        )->fetchArray(\LibSQL::LIBSQL_ASSOC);

        return !empty($exists);
    }
    
    /**
     * Generates a libSQL Server Database token for Local Development.
     *
     * This method will generate a full access and read only token for the given database name.
     * The expiration time for the token is set by the setTokenExpiration method of this class.
     * The method will check if the database name already exists in the token store, and if it does,
     * it will exit with an error message.
     *
     * @param string $dbName The database name to generate the token for.
     * @param bool $displayTable If true, the method will display the generated tokens in a table.
     *
     * @return void
     */
    public function generete(string $dbName, bool $displayTable = true): void
    {
        // Check if database already exists
        $exists = $this->tokenStore->query(
            "SELECT * FROM tokens WHERE db_name = ?",
            [$dbName]
        )->fetchArray(\LibSQL::LIBSQL_ASSOC);

        if (!empty($exists)) {
            error(' 🚫 Oops: Database already exists');
            exit;
        }

        warning(" ⌛ Creating libSQL Server Database token for Local Development...");

        $tokenExpiration = $this->tokenExpiration;
        $fullAccessToken = (new JwtFacade())->issue(
            new Eddsa(),
            $this->key,
            static fn(
            Builder $builder,
            DateTimeImmutable $issuedAt
        ): Builder => $builder
                ->identifiedBy($dbName)
                ->expiresAt($issuedAt->modify("+{$tokenExpiration} days"))
        );

        $readOnlyToken = (new JwtFacade())->issue(
            new Eddsa(),
            $this->key,
            static fn(
            Builder $builder,
            DateTimeImmutable $issuedAt
        ): Builder => $builder
                ->identifiedBy($dbName)
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
            'expiration_day' => $tokenExpiration,
        ];

        if ($displayTable) {
            $this->displayTable();
        }
    }

    /**
     * Displays the generated database tokens in a user-friendly format.
     * 
     * @return void
     */
    public function displayTable(): void
    {
        $this->tokenStore->execute(sql_file('create_new_token'), array_values($this->results));

        $message = <<<MSG
 [SUCCESS]
 
 Your database token successfully generated, now you can show the token using the command:
 
 $ turso-php-installer token:show <db_name> --fat | --roa | --pkp | --pkb
 
 where:
 + db_name - database name
 + --fat - full access token
 + --roa - read-only access token
 + --pkp - public key pem
 + --pkb - public key base64
 
 Read more: 
 $ turso-php-installer token:show --help
 -------------------------------------------------------------------------
 + full_access_token or read_only_token - in your .env file
 + public_key_pem - create a file jwt_public.pem in your desire directory and save this value
 Source Explantion: https://gist.github.com/darkterminal/c272bf2a572bc5d7378f31cf4aea5f19
 -------------------------------------------------------------------------
MSG;
        info($message);
        info(' List all generated tokens:');
        $this->listAllTokens();
    }

    /**
     * Returns the generated database tokens.
     *
     * @param string|null $key If specified, returns the value of the given key from the token store. Otherwise, returns the entire token store.
     *
     * @return void
     */
    public function getToken(string $db_name, string $key = null): void
    {
        $store = $this->tokenStore->query(sql_file('get_database_token'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
        $store = (array) collect($store)->first();

        if ($key) {
            echo collect($store)->get($key);
            return;
        }
        echo collect($store)->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ;
    }

    /**
     * Returns the generated database token.
     *
     * If $key is specified, returns the value of the given key from the token store. Otherwise, returns the entire token store as a JSON string.
     *
     * @param string $db_name The name of the database to get the token for.
     * @param string|null $key If specified, returns the value of the given key from the token store. Otherwise, returns the entire token store as a JSON string.
     *
     * @return string
     */
    public function getRawToken(string $db_name, string $key = null): string
    {
        $store = $this->tokenStore->query(sql_file('get_database_token'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
        $store = (array) collect($store)->first();

        if ($key) {
            return collect($store)->get($key);
        }
        return collect($store)->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ;
    }

    /**
     * Shows all generated database tokens.
     *
     * This method will display a table with the following columns:
     *  - db_name: The name of the database.
     *  - expired_at: The date and time the token will expire. Also shows the time difference between now and expiration date.
     *  - created_at: The date and time the token was created.
     *  - updated_at: The date and time the token was last updated.
     *
     * @return void
     */
    public function listAllTokens(): void
    {
        $store = $this->tokenStore->query(sql_file('get_all_database_token'))->fetchArray(\LibSQL::LIBSQL_ASSOC);
        echo table(
            ['db_name', 'expired_at', 'created_at', 'updated_at'],
            collect($store)->map(function ($item) {
                $date_expiration = Carbon::parse($item['created_at'])->setTimezone(config('app.timezone'))->addDays($item['expiration_day']);
                return [
                    'db_name' => $item['db_name'],
                    'expired_at' => $date_expiration->format('Y-m-d H:i:s') . ' (' . $date_expiration->diffForHumans() . ')',
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'],
                ];
            })->toArray()
        );
    }

    /**
     * Delete a database token.
     *
     * This method will delete a database token from the token store.
     *
     * If the token does not exist, it will show an error message and exit with error code 1.
     *
     * @param string $db_name The name of the database to delete the token for.
     *
     * @return void
     */
    public function deleteToken(string $db_name): void
    {
        $result = $this->tokenStore->query(sql_file('check_database_name'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
        if (empty($result)) {
            error(" 🚫 Not found: your're not generated token yet. Run 'turso-php-installer token:create' command first");
            exit;
        }
        $this->tokenStore->execute(sql_file('delete_database_token'), [$db_name]);
    }

    /**
     * Delete all database tokens.
     *
     * This method will delete all database tokens from the token store.
     *
     * @return void
     */
    public function deleteAllTokens(): void
    {
        $this->tokenStore->execute('DELETE FROM tokens');
        $this->listAllTokens();
    }

    /**
     * Clean up resources when the object is no longer needed.
     *
     * This method will delete the temporary files created when generating a
     * new token, and reset the class properties to their default values.
     *
     * @return void
     */
    public function __destruct()
    {
        unlink("{$this->temp_dir}/jwt_public.pem");
        unlink("{$this->temp_dir}/jwt_private.pem");
        $this->tokenExpiration = 7;
        $this->privateKey = null;
        $this->publicKeyPem = null;
        $this->pubKeyBase64 = null;
        $this->key = null;
        $this->temp_dir = null;
        $this->results = [];
    }
}
