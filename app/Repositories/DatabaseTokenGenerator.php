<?php

namespace App\Repositories;

use DateTimeImmutable;
use Lcobucci\JWT\JwtFacade;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;

use function Laravel\Prompts\info;

final class DatabaseTokenGenerator
{
    public int $tokenExpiration = 7;

    protected ?string $privateKey = null;
    protected ?string $publicKeyPem = null;
    protected ?string $pubKeyBase64 = null;
    protected ?InMemory $key = null;
    protected ?string $tempDir = null;

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
                die("Error: PHP extension '$ext' is not installed or enabled." . PHP_EOL);
            }
        }

        if (!shell_exec('which openssl')) {
            die("Error: OpenSSL command-line tool is not installed or not in your PATH." . PHP_EOL);
        }

        $this->tempDir = sys_get_temp_dir();
        $this->generatePublicAndPrivateKey();
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
    public function generete()
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
        $results = $pretty_print ? json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : json_encode($this->results);
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
