<?php

namespace Darkterminal\TursoLibSQLInstaller\Services\Generators;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;

use function Laravel\Prompts\info;

final class DatabaseTokenGenerator
{
    public int $token_expiration = 7;

    protected string|null $privateKey = null;
    protected string|null $publicKey = null;
    protected string|null $pubKeyPem = null;
    protected string|null $pubKeyBase64 = null;

    protected array $results = [];

    /**
     * Initializes the DatabaseTokenGenerator instance by generating an Ed25519 key pair and encoding the public key.
     *
     * @return void
     */
    public function __construct()
    {
        $this->generateEd25519();
        $this->encodePublicKey();
    }

    /**
     * Generates an Ed25519 key pair and sets the private and public keys.
     *
     * @return void
     */
    protected function generateEd25519()
    {
        $keyPair = sodium_crypto_sign_keypair();
        $this->privateKey = sodium_crypto_sign_secretkey($keyPair);
        $this->publicKey = sodium_crypto_sign_publickey($keyPair);
    }

    /**
     * Encodes the public key in PEM and Base64 formats.
     *
     * @return void
     */
    protected function encodePublicKey()
    {
        $this->pubKeyPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($this->publicKey), 64, "\n") . "-----END PUBLIC KEY-----";
        $this->pubKeyBase64 = rtrim(strtr(base64_encode($this->publicKey), '+/', '-_'), '=');
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
        $now = new DateTimeImmutable();
        $exp = $now->modify("+{$this->token_expiration} days")->getTimestamp();

        $signer = new Eddsa();
        $key = InMemory::plainText($this->privateKey);

        $config = Configuration::forSymmetricSigner($signer, $key);
        $fullAccessToken = $config->builder()
            ->issuedAt($now)
            ->expiresAt((new DateTimeImmutable())->setTimestamp($exp))
            ->getToken($config->signer(), $config->signingKey());

        $readOnlyToken = $config->builder()
            ->issuedAt($now)
            ->expiresAt((new DateTimeImmutable())->setTimestamp($exp))
            ->withClaim('a', 'ro')
            ->getToken($config->signer(), $config->signingKey());

        $this->results = [
            'full_access_token' => $fullAccessToken->toString(),
            'read_only_token' => $readOnlyToken->toString(),
            'public_key_pem' => $this->pubKeyPem,
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
+ public_key_pem or public_key_base64 - in your desire directory
And used with Turso DEV CLI, source explantion: https://gist.github.com/darkterminal/c272bf2a572bc5d7378f31cf4aea5f19
-------------------------------------------------------------------------
MSG;
        info($message);
        echo $results . PHP_EOL;
    }

    /**
     * Archives the generated database token to a specified directory.
     *
     * If no directory is provided, it defaults to the current working directory.
     * The archive includes a .env-example file with the full access token and read-only token,
     * as well as jwt_key.pem and jwt_key.base64 files containing the public key.
     *
     * @param string|null $location_directory The directory where the archive will be created. Defaults to null.
     * @return void
     */
    public function toArchive(string|null $location_directory = null)
    {
        $location = is_null($location_directory) ? getcwd() : $location_directory;

        if (is_null($location_directory)) {
            $location = "$location/.archive";
            mkdir($location);
        }

        if (!is_dir($location)) {
            mkdir($location);
        }

        $env_file = "$location/.env-example";
        if (!file_exists($env_file)) {
            touch($env_file);
        }

        file_put_contents($env_file, "TURSO_AUTH_TOKEN={$this->results['full_access_token']}\n", FILE_APPEND);
        file_put_contents($env_file, "TURSO_AUTH_TOKEN_READ_ONLY={$this->results['read_only_token']}\n", FILE_APPEND);

        $pem_file = "$location/jwt_key.pem";
        if (!file_exists($pem_file)) {
            touch($pem_file);
        }

        $base64_file = "$location/jwt_key.base64";
        if (!file_exists($base64_file)) {
            touch($base64_file);
        }

        file_put_contents($pem_file, $this->results['public_key_pem'], FILE_APPEND);
        file_put_contents($base64_file, $this->results['public_key_base64'], FILE_APPEND);

        $message = <<<MSG
[SUCCESS]

Your database token successfully generated, now you can used in Turso DEV CLI
+ full_access_token or read_only_token - in your .env file
+ public_key_pem or public_key_base64 - in your desire directory
source explantion: https://gist.github.com/darkterminal/c272bf2a572bc5d7378f31cf4aea5f19
-----------------------------------------------------------------------------
MSG;
        info($message);
        echo "Archive created at $location\n";
    }
}
