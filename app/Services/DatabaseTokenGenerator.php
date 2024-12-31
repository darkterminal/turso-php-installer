<?php

namespace App\Services;

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

final class DatabaseTokenGenerator
{
    public int $tokenExpiration = 7;

    protected ?string $privateKey = null;
    protected ?string $publicKeyPem = null;
    protected ?string $pubKeyBase64 = null;
    protected ?InMemory $key = null;
    protected ?string $temp_dir = null;
    protected string $home;
    protected \LibSQL $tokenStore;

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
     * Generates database tokens with full access and read-only permissions.
     *
     * The function creates a full access token and a read-only token, both of which are set to expire after a specified number of days.
     * The tokens are generated using a symmetric signer with a private key.
     * The function returns the current instance with the generated tokens stored in the results array.
     *
     * @return void
     */
    public function generete(string $dbName): void
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

        $this->displayTable();
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

    public function listAllTokens()
    {
        $store = $this->tokenStore->query(sql_file('get_all_database_token'))->fetchArray(\LibSQL::LIBSQL_ASSOC);
        echo table(
            ['db_name', 'expired_at', 'created_at', 'updated_at'],
            collect($store)->map(function ($item) {
                $date_expiration = Carbon::parse($item['created_at'])->addDays($item['expiration_day']);
                return [
                    'db_name' => $item['db_name'],
                    'expired_at' => $date_expiration->format('Y-m-d H:i:s') . ' (' . $date_expiration->ago() . ')',
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'],
                ];
            })->toArray()
        );
    }

    public function deleteToken(string $db_name)
    {
        $result = $this->tokenStore->query(sql_file('check_database_name'), [$db_name])->fetchArray(\LibSQL::LIBSQL_ASSOC);
        if (empty($result)) {
            error(" 🚫 Not found: your're not generated token yet. Run 'turso-php-installer token:create' command first");
            exit;
        }
        $this->tokenStore->execute(sql_file('delete_database_token'), [$db_name]);
    }

    public function deleteAllTokens()
    {
        $this->tokenStore->execute('DELETE FROM tokens');
        $this->listAllTokens();
    }

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
