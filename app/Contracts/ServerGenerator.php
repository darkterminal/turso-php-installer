<?php

namespace App\Contracts;

/**
 * Interface ServerGenerator.
 *
 * This interface is used to generate a CA certificate and its private key,
 * and to list all CA certificates in the certificate store.
 */
interface ServerGenerator
{
    /**
     * Checks that the required Python packages are installed.
     *
     * This check is skipped if the command fails to execute.
     *
     * @return bool True if the packages are installed, false otherwise.
     */
    public function checkRequirement(): bool;

    /**
     * Set the location of the certificate store.
     *
     * @param string $cert_store_location The location of the certificate store.
     *
     * @return void
     */
    public function setCertStoreLocation(string $cert_store_location): void;

    /**
     * Create a new CA certificate.
     *
     * @param string $name The name of the CA certificate.
     * @param int $expiration The number of days the CA certificate is valid for.
     *
     * @return bool True if the CA certificate was created successfully, false otherwise.
     */
    public function createCaCert(string $name, int $expiration): bool;

    /**
     * Create a new peer certificate.
     *
     * @param string $name The name of the peer certificate.
     * @param int $expiration The number of days the peer certificate is valid for.
     *
     * @return bool True if the peer certificate was created successfully, false otherwise.
     */
    public function createPeerCert(string $name, int $expiration): bool;

    /**
     * Show the CA certificate.
     *
     * @param bool $raw If true, the raw CA certificate is shown, otherwise its details.
     *
     * @return void
     */
    public function showCaCert(bool $raw = true): void;

    /**
     * Delete a CA certificate.
     *
     * @param string $name The name of the CA certificate.
     * @param bool $all If true, all CA certificates are deleted.
     *
     * @return void
     */
    public function deleteCaCert(string $name, bool $all): void;

    /**
     * List all CA certificates in the certificate store.
     *
     * @return void
     */
    public function listCaCert(): void;
}
