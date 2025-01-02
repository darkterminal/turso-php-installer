<?php

namespace App\Services\LibsqlServer;

use App\Contracts\ServerGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\error;
use function Laravel\Prompts\warning;

/**
 * Class LibsqlServerGenerator.
 *
 * This class is used to generate a CA certificate and its private key,
 * and to list all CA certificates in the certificate store.
 */
class LibsqlServerGenerator implements ServerGenerator
{
    /**
     * Checks that the required Python packages are installed.
     *
     * This check is skipped if the command fails to execute.
     *
     * @return bool True if the packages are installed, false otherwise.
     */
    public function checkRequirement(): bool
    {
        $pythonCommand = Process::run('python --version');
        if (!$pythonCommand->successful()) {
            warning(' ❕ Python is not installed. Please install Python before running this command.');
            return false;
        }
        $requirementsCommand = Process::run('python ' . config('app.scripts') . DS . 'check_requirement.py');
        if (!$requirementsCommand->successful()) {
            warning(' ❕The required [cryptography] library are not installed. Please install the required [cryptography] library before running this command.');
            return false;
        }
        return true;
    }

    /**
     * Set the location of the certificate store.
     *
     * @param string $cert_store_location The location of the certificate store.
     *
     * @return void
     */
    public function setCertStoreLocation(string $cert_store_location): void
    {
        $metadata = json_decode(file_get_contents(config('app.metadata')), true);
        $metadata['cert_store_location'] = $cert_store_location;
        file_put_contents(config('app.metadata'), json_encode($metadata));
        info(" 📁 Setting cert store location to $cert_store_location");
    }

    /**
     * Create a new CA certificate.
     *
     * @param string $name The name of the CA certificate.
     * @param int $expiration The number of days the CA certificate is valid for.
     *
     * @return bool True if the CA certificate was created successfully, false otherwise.
     */
    public function createCaCert(string $name, int $expiration): bool
    {
        $name = "ca"; // for now only ca is supported
        $expiration_days = (int) $expiration;
        $store_location = get_global_metadata('cert_store_location');

        // TODO: Check for later, I think it's need to be store in database. But, I will think about it!
        // if (file_exists($store_location . DS . "$name.pem")) {
        //     info(" 🫣 CA certificate already exists at $store_location");
        //     return false;
        // }

        $command = Process::run('python ' . config('app.scripts') . DS . 'ca_cert_create.py --custom-name ' . $name . ' --days ' . $expiration_days . ' --store-location ' . $store_location);
        return $command->seeInOutput('Stored cert');
    }

    /**
     * Create a new peer certificate.
     *
     * @param string $name The name of the peer certificate.
     * @param int $expiration The number of days the peer certificate is valid for.
     *
     * @return bool True if the peer certificate was created successfully, false otherwise.
     */
    public function createPeerCert(string $name, int $expiration): bool
    {
        /**
         * NOTE:
         * 
         * Fro now when the peer certificate is created, it will be stored in the certificate store.
         * The name of the certificate will be the name of the peer certificate and should be extended with "ca_cert.pem"
         * and "ca_key.pem".
         * 
         * But, in the future, when the peer certificate is created, it will be stored in the database.
         * The name of the certificate will be the name of the peer certificate and should be extended with parent CA cert & key.
         * 
         */

        $expiration_days = (int) $expiration;
        $store_location = get_global_metadata('cert_store_location');

        if (!file_exists($store_location . DS . "ca_cert.pem") || !file_exists($store_location . DS . "ca_key.pem")) {
            error(" 🫣  CA certificate not found at: \n Store Location: $store_location \n It's required to generate peer cert.");
            exit;
        }

        $defaultPeerName = faker()->domainWord();
        $defaultCertFileName = $name === 'ca' ? $defaultPeerName : $name;

        if (file_exists($store_location . DS . "$defaultCertFileName.pem")) {
            info(" 🫣 Peer certificate already exists at $store_location");
            exit;
        }

        $command = Process::run('python ' . config('app.scripts') . DS . 'peer_cert_create.py --peer-name ' . $defaultPeerName . ' --custom-name ' . $defaultCertFileName . ' --days ' . $expiration_days . ' --store-location ' . $store_location);
        return $command->seeInOutput('Stored cert');
    }

    /**
     * Display the CA certificate and its details.
     *
     * If the $raw parameter is true, this function outputs the raw content
     * of the CA certificate and its private key from the certificate store.
     * Otherwise, it runs an external script to show a detailed view of the
     * certificate.
     *
     * @param bool $raw If true, shows raw certificate content; if false, shows detailed information.
     *
     * @return void
     */
    public function showCaCert(bool $raw = true): void
    {
        $store_location = get_global_metadata('cert_store_location');

        if ($raw) {
            info(" 🗂️ CA certificate store at $store_location");
            echo " 🎖 CA certificate:\n\n";
            echo file_get_contents($store_location . DS . "ca_cert.pem");
            echo "\n 🔏 CA private key:\n\n";
            echo file_get_contents($store_location . DS . "ca_key.pem");
            return;
        }

        $command = Process::run('python ' . config('app.scripts') . DS . 'ca_cert_show.py --store-location ' . $store_location);
        info(" 📝 Details of CA certificate store at $store_location");
        echo $command->output();
    }

    /**
     * Deletes one or all CA certificates from the certificate store.
     *
     * If the $all parameter is true, this function deletes all CA certificates
     * in the store after user confirmation. Otherwise, it deletes the CA
     * certificate specified by $name.
     *
     * @param string $name The name of the CA certificate to delete.
     * @param bool $all If true, deletes all CA certificates; otherwise, deletes the specified certificate.
     *
     * @return void
     */
    public function deleteCaCert(string $name, bool $all): void
    {
        $store_location = get_global_metadata('cert_store_location');

        if ($all) {
            if (!confirm('Are you sure you want to delete all CA certificates?')) {
                return;
            }

            $files = glob($store_location . DS . "*.pem");
            File::delete($files);
            info(" ✨ Deleted all CA certificates at $store_location");
            return;
        }

        $files = glob($store_location . DS . "$name*.pem");
        if (count($files) === 0) {
            info(" 🫣 No CA certificate found at $store_location");
            return;
        }

        File::delete($files);
        info("  ✨ Deleted CA certificate at $store_location");
    }

    /**
     * List all CA certificates in the certificate store.
     *
     * This function shows the list of CA certificates in the certificate store,
     * along with their expiration dates. The expiration date is displayed in
     * the default timezone in your php.ini file.
     *
     * If no CA certificates are found, this function displays an error message.
     *
     * @return void
     */
    public function listCaCert(): void
    {
        $store_location = get_global_metadata('cert_store_location');

        $command = Process::run('python ' . config('app.scripts') . DS . 'ca_cert_list.py --store-location ' . $store_location);

        if ($command->successful()) {
            info(" 🗂️ List of CA certificates at $store_location");
            $list_cacert_results = json_decode(file_get_contents(sys_get_temp_dir() . DS . "list_cacert_results.json"), true);
            table(
                headers: ['Filename', 'Valid'],
                rows: collect($list_cacert_results)->map(function ($item) {
                    $not_valid_after = Carbon::parse($item['not_valid_after'])->setTimezone(config('app.timezone'));
                    return [
                        Str::replaceLast('.pem', '', $item['file_name']),
                        $not_valid_after->diffForHumans()
                    ];
                })
            );
            info(" 📝 : The expiration date is displayed by default timezone in your php.ini file.");
            File::delete(sys_get_temp_dir() . DS . "list_cacert_results.json");
            return;
        }
        error(" 🫣 No CA certificate found at $store_location");
    }
}
