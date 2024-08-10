<?php

namespace Darkterminal\TursoLibSQLInstaller\Services\Generators;

use Exception;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;

/**
 * Utility that generates X.509 certificates for testing.
 * The following certificates and their keys are stored in your working directory:
 * - ca_cert.pem, ca_key.pem
 * - server_cert.pem, server_key.pem
 * - client_cert.pem, client_key.pem
 */
class CertificateGenerator
{
    public int $certValidity = 30; // in a days
    public string $certDirectory;
    public bool|OpenSSLAsymmetricKey $caKey = false;
    public bool|OpenSSLCertificate $caCert = false;
    public bool|OpenSSLCertificate $peerCert = false;

    /**
     * Constructor for the CertificateGenerator class.
     *
     * Initializes the certificate directory and generates a private key for the CA.
     *
     * @return void
     */
    public function __construct()
    {
        $this->certDirectory = getcwd() . '/.archive';
        $this->caKey = $this->generatePrivateKey();
    }

    /**
     * Generates the necessary X.509 certificates for testing.
     * 
     * This function generates the CA certificate, server certificate, and client certificate.
     * It also outputs the expiration date of the certificates.
     * 
     * @return void
     */
    public function generate()
    {
        $this->generateCaCertificate();
        $this->generatePeerCertificate('server', 'sqld', ['sqld']);
        $this->generatePeerCertificate('client', 'sqld replica', []);

        $now = new \DateTimeImmutable();
        $exp = $now->modify("+{$this->certValidity} days")->getTimestamp();
        echo "These are development certs, they will expire at: " . date('Y-m-d H:i:s', $exp) . PHP_EOL;
    }

    /**
     * Generates a random serial number.
     *
     * @return int A random serial number.
     */
    protected function generateSerialNumber()
    {
        $serial_number = hexdec(bin2hex(openssl_random_pseudo_bytes(8)));
        return (int) $serial_number;
    }

    /**
     * Generates a new private key using the OpenSSL library.
     *
     * @throws Exception If the private key generation fails.
     * @return OpenSSLAsymmetricKey The newly generated private key.
     */
    protected function generatePrivateKey()
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $key = openssl_pkey_new($config);

        if (!$key) {
            throw new Exception('Failed to generate private key: ' . openssl_error_string());
        }

        return $key;
    }

    /**
     * Generates a Certificate Authority (CA) certificate.
     *
     * This function creates a CA certificate using the provided private key and
     * configuration settings. It generates a certificate signing request (CSR),
     * signs the CSR with the private key, and stores the resulting CA certificate.
     *
     * @throws Exception If the CSR generation or certificate signing fails.
     * @return void
     */
    protected function generateCaCertificate()
    {
        $ca_key = $this->caKey;
        $days = $this->certValidity;
        $dn = ['commonName' => 'sqld dev CA'];
        $csr = openssl_csr_new($dn, $ca_key, ['digest_alg' => 'sha256']);

        if (!$csr) {
            throw new Exception('Failed to generate CSR: ' . openssl_error_string());
        }

        $serial_number = $this->generateSerialNumber();
        $ca_cert = openssl_csr_sign($csr, null, $ca_key, $days, [
            'x509_extensions' => [
                'basicConstraints' => 'CA:TRUE',
                'keyUsage' => 'keyCertSign, cRLSign',
            ],
        ], $serial_number);

        if (!$ca_cert) {
            throw new Exception('Failed to sign CA certificate: ' . openssl_error_string());
        }

        $this->caCert = $ca_cert;
        $this->createCertificate([$ca_cert], $ca_key, 'ca');
    }

    /**
     * Generates a peer certificate using the provided parameters.
     *
     * This function creates a certificate signing request (CSR), signs the CSR with
     * the CA certificate, and stores the resulting peer certificate.
     *
     * @param string $name The name of the peer certificate.
     * @param string $peer_common_name The common name of the peer certificate.
     * @param array $peer_dns_names The DNS names of the peer certificate.
     * @throws Exception If the CSR generation or certificate signing fails.
     * @return void
     */
    protected function generatePeerCertificate(string $name, string $peer_common_name, array $peer_dns_names = [])
    {
        $days = $this->certValidity;
        $dn = ['commonName' => $peer_common_name];
        $csr = openssl_csr_new($dn, $peer_key, ['digest_alg' => 'sha256']);

        if (!$csr) {
            throw new Exception('Failed to generate CSR: ' . openssl_error_string());
        }

        $san = implode(',', array_map(fn($dns) => "DNS:$dns", $peer_dns_names));
        $serial_number = $this->generateSerialNumber();
        $peer_cert = openssl_csr_sign($csr, $this->caCert, $this->caKey, $days, [
            'x509_extensions' => [
                'basicConstraints' => 'CA:FALSE',
                'keyUsage' => 'digitalSignature',
                'subjectAltName' => $san,
            ],
        ], $serial_number);

        if (!$peer_cert) {
            throw new Exception('Failed to sign peer certificate: ' . openssl_error_string());
        }

        $this->peerCert = $peer_cert;
        $this->createCertificate([$this->peerCert, $this->caCert], $this->generatePrivateKey(), $name);
    }

    /**
     * Creates a certificate file and private key file for the given certificate chain and key.
     *
     * @param array $cert_chain The array of certificates to be stored.
     * @param bool|OpenSSLAsymmetricKey $key The private key to be stored.
     * @param string $name The name of the certificate and key files.
     * @throws Exception If there are issues storing the certificate or private key.
     */
    protected function createCertificate(array $cert_chain, bool|OpenSSLAsymmetricKey $key, string $name)
    {
        $certs_dir = "{$this->certDirectory}/certs";
        if (!is_dir($certs_dir)) {
            mkdir($certs_dir);
        }

        $cert_file = "{$certs_dir}/{$name}_cert.pem";
        $key_file = "{$certs_dir}/{$name}_key.pem";

        foreach ($cert_chain as $cert) {
            if (!openssl_x509_export_to_file($cert, $cert_file)) {
                throw new Exception("Failed to store certificate $name: " . openssl_error_string());
            }
        }

        if (!openssl_pkey_export_to_file($key, $key_file)) {
            throw new Exception("Failed to store private key $name: " . openssl_error_string());
        }
    }
}
