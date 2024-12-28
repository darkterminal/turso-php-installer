<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Http;
use ZipArchive;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class Installer
{
    public const VERSION = '2.0.3';
    private string $repo;
    private string $os;
    private string $arch;
    private string $home;
    private string $currentVersion;
    private string $selectedPhpVersion;
    private string $binaryName;
    private string $destination;
    private array $configIni;
    private ?string $isAlreadyExists;
    private string $moduleFile;
    private string $phpIni;
    private string $extensionArchive;

    /**
     * Initializes the TursoLibSQLInstaller class.
     *
     * Sets various class properties, including the repository URL, operating system,
     * architecture, home directory, PHP version, binary name, destination directory,
     * Herd path, and configuration INI file.
     *
     * @return void
     */
    public function __construct()
    {
        $this->repo = "https://raw.githubusercontent.com/tursodatabase/turso-client-php/main/release_metadata.json";
        $this->os = strtolower(php_uname('s'));
        $this->arch = php_uname('m');
        $this->home = $this->checkIsWindows() ? $this->home = getenv('USERPROFILE') : trim(shell_exec('echo $HOME'));
        $this->currentVersion = implode('.', array_slice(explode('.', string: PHP_VERSION), 0, -1));
        $this->selectedPhpVersion = $this->currentVersion;
        $this->binaryName = $this->checkIsWindows() ? "libsql_php.dll" : "liblibsql_php.so";
        $this->herdPath = $this->checkIsWindows() ? "{$this->home}". DIRECTORY_SEPARATOR .".config". DIRECTORY_SEPARATOR ."herd" : "{$this->home}". DIRECTORY_SEPARATOR ."Library". DIRECTORY_SEPARATOR ."Application Support". DIRECTORY_SEPARATOR ."Herd";
        $this->binaryName = "liblibsql_php.so";
        $this->destination = $this->home. DIRECTORY_SEPARATOR .".config". DIRECTORY_SEPARATOR .".turso-client-php";
        $this->configIni = $this->getConfigIni();
        $this->isAlreadyExists = $this->checkIsAlreadyExists();
        $this->moduleFile = "extension={$this->destination}" . DIRECTORY_SEPARATOR . "{$this->binaryName}";
        $this->phpIni = $this->configIni['loaded_configuration_file'] ?? '';
    }

    /**
     * Outputs the version number of the Turso libSQL Installer.
     *
     * @return void
     */
    public function version(): void
    {
        info("Turso libSQL Installer (version: " . self::VERSION . ")");
    }

    /**
     * Installs the Turso libSQL Extension for PHP.
     *
     * This function checks if the operating system is Windows and if the current
     * directory is a Laravel Herd. If not, it checks if the installation is already
     * performed. It then checks the specified PHP version and sets it if it is
     * supported. If no version is specified, it checks the PHP version. It also
     * checks if the PHP INI file exists and if the required functions are available.
     * If the installation is not automatically confirmed, it asks for permission.
     * It displays information about the installation and downloads and extracts the
     * binary.
     *
     * @param bool $autoConfirm Whether to automatically confirm the installation.
     * @param string|null $specifiedVersion The specified PHP version.
     * @throws \Exception If the specified version is not supported.
     * @return void
     */
    public function install(
        bool $autoConfirm = false,
        string|null $specifiedVersion = null,
        string|null $phpIniFile = null,
        string|null $extDestination = null
    ): void {
        $this->checkIsWindows();

        $this->destination = $extDestination ?? $this->destination;
        $this->phpIni = $phpIniFile ?? $this->phpIni;
        $this->moduleFile = "extension={$this->destination}" . DIRECTORY_SEPARATOR . "{$this->binaryName}";

        if (!$autoConfirm) {
            $this->checkIsAlreadyExists();
        }

        if ($specifiedVersion !== null) {
            if (in_array($specifiedVersion, ['8.0', '8.1', '8.2', '8.3'])) {
                $this->selectedPhpVersion = $specifiedVersion;
            } else {
                error("Specified version $specifiedVersion is not supported.");
                exit;
            }
        } else {
            $this->checkPhpVersion();
        }

        $this->checkIsPhpIniExists();
        $this->checkFunctionRequirements();

        if (!$autoConfirm) {
            $this->askInstallPermission();
        }

        $this->displayInfo();
        $this->downloadAndExtractBinary();
    }

    /**
     * Updates the Turso libSQL Extension for PHP.
     *
     * Checks if the extension is already installed and updates it if necessary.
     *
     * @throws \Exception If the extension is not installed before updating.
     * @return void
     */
    public function update(): void
    {
        $isFound = $this->checkIsAlreadyExists();
        if ($isFound) {
            $this->downloadAndExtractBinary(true);
            exit;
        }
        error("You doesn't have Turso libSQL Extension installed before.");
    }

    /**
     * Uninstalls the Turso libSQL Extension for PHP.
     *
     * Checks if the extension is already installed and uninstalls it if necessary.
     * Prompts the user for sudo permission to remove the extension.
     *
     * @throws \Exception If the extension is not installed before uninstalling.
     * @return void
     */
    public function uninstall(): void
    {
        $isFound = $this->checkIsAlreadyExists();
        if ($isFound) {

            $confirmUninstall = confirm(
                label: 'To uninstall the extension, Turso needs your sudo permission. Continue?',
                default: true
            );

            $metadata = $this->getMetadataFile();
            $destination = $metadata['destination'] ?? $this->destination;
            $this->moduleFile = "extension={$destination}" . DIRECTORY_SEPARATOR . "{$this->binaryName}";

            if ($confirmUninstall) {
                if ($this->checkIsWindows()) {
                    $escapedModuleFile = str_replace(['/', '\\'], '\\', $this->moduleFile);
                    $phpIni = $this->phpIni;
                    // Check if the php.ini file exists
                    if (file_exists($phpIni)) {
                        // Read the content of the php.ini file
                        $iniContents = file_get_contents($phpIni);

                        // Escape special characters in $escapedModuleFile to avoid issues with preg_replace
                        $escapedModuleFileSafe = preg_quote($escapedModuleFile, '/');

                        // Check if the module file exists in the php.ini
                        if (strpos($iniContents, $escapedModuleFile) !== false) {
                            // Remove the line containing the module file
                            $updatedContents = preg_replace("/^.*$escapedModuleFileSafe.*$/m", '', $iniContents);

                            // Write the updated contents back to php.ini
                            file_put_contents($phpIni, $updatedContents);

                            $this->removeDirectory($this->destination);

                            info(
                                message: "Removed extension line from {$phpIni}\nTHANK YOU FOR USING TURSO libSQL Extension for PHP"
                            );
                        } else {
                            error("No line found for $escapedModuleFile in $phpIni");
                        }
                    } else {
                        error("php.ini file not found at $phpIni\n");
                    }
                } else {
                    $escapedModuleFile = str_replace('/', '\/', $this->moduleFile);
                    $phpIni = $this->phpIni;

                    $hasSudo = shell_exec("sudo -v");
                    if ($hasSudo === 0) {
                        $command = "sudo -S bash -c \"sed -i '/$escapedModuleFile/d' {$phpIni}\"";
                        shell_exec($command);
                    } else {
                        echo "Type your ";
                        $command = "sudo -S bash -c \"sed -i '/$escapedModuleFile/d' {$phpIni}\" && sudo -k";
                        shell_exec($command);
                    }

                    shell_exec(command: "rm -rf {$destination}/*");
                    $this->removeDirectory("{$this->home}" . DIRECTORY_SEPARATOR . ".tpi-metadata");

                    info(
                        message: "Removed extension line from {$phpIni}\nTHANK YOU FOR USING TURSO libSQL Extension for PHP"
                    );
                }
            } else {
                info(message: "Uninstallation cancelled.");
            }
        } else {
            error(message: "You don't have Turso libSQL Extension installed before.");
        }
    }

    /**
     * Recursively removes a directory and all its contents, including hidden files and subdirectories.
     *
     * @param string $dir The path to the directory to be removed.
     * @return bool True if the directory is successfully removed, false otherwise.
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = array_diff(scandir($dir, 1), ['.', '..']); // Exclude '.' and '..'
        foreach ($items as $item) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($filePath)) {
                // Recursive call for subdirectories
                if (!$this->removeDirectory($filePath)) {
                    return false;
                }
            } else {
                // Delete file, including hidden ones
                if (!unlink($filePath)) {
                    return false;
                }
            }
        }

        // Remove the directory itself
        return @rmdir($dir);
    }

    /**
     * Checks if the current operating system is Windows and displays a warning message if true.
     *
     * @return bool
     */
    private function checkIsWindows(): bool
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        return $isWindows;
    }

    /**
     * Checks the PHP version and sets the selected version.
     *
     * Presents the user with a selection of supported PHP versions and sets the
     * selected version based on the user's input.
     *
     * @return void
     */
    private function checkPhpVersion(): void
    {
        $phpVersions = ['8.0', '8.1', '8.2', '8.3'];
        $versionSelected = select(
            label: 'Select libSQL for PHP Version',
            options: $phpVersions,
            default: $this->currentVersion,
        );
        $this->selectedPhpVersion = $versionSelected;
    }

    /**
     * Checks if the required PHP functions 'shell_exec' and 'curl_version' are enabled.
     *
     * If either of these functions are disabled, it provides instructions on how to enable them in the 'php.ini' file.
     *
     * @throws \Exception if 'shell_exec' or 'curl_version' are disabled and the user is unable to enable them.
     * @return void
     */
    private function checkFunctionRequirements(): void
    {
        if (!function_exists('shell_exec') && !function_exists('curl_version')) {
            $message = <<<ERR_FUNC_NOT_FOUND
            [ERROR]

            It looks like the 'shell_exec' and 'curl_version' functions are disabled in your PHP environment. These functions are essential for this script to work properly.
            To enable them, follow these steps:
            1. Open your 'php.ini' file. You can find the location of your 'php.ini' file by running the command 'php --ini' in your terminal or command prompt.
            2. Search for 'disable_functions' directive. It might look something like this:
            disable_functions = shell_exec, curl_version
            3. Remove 'shell_exec' and 'curl_version' from this list. It should look like:
            disable_functions =
            4. Save the 'php.ini' file.
            5. Restart your web server for the changes to take effect. If you are using Apache, you can restart it with:
            sudo service apache2 restart
            or for Nginx:
            sudo service nginx restart
            If you are using a web hosting service, you might need to contact your hosting provider to enable these functions for you.
            For more information on 'shell_exec', visit: https://www.php.net/manual/en/function.shell-exec.php
            For more information on 'curl_version', visit: https://www.php.net/manual/en/function.curl-version.php

            Thank you!

            ERR_FUNC_NOT_FOUND;
            error($message);
            exit;
        }
    }

    /**
     * Checks if the Turso/libSQL Client PHP is already installed and configured.
     *
     * @return void
     */
    private function checkIfAlreadyInstalled(): void
    {
        if (!empty($this->isAlreadyExists)) {
            $message = <<<INFO_ALREADY_INSTALL
            [INFO]
            
            Turso/libSQL Client PHP is already installed and configured!
            INFO_ALREADY_INSTALL;
            info($message);
            exit;
        }
    }

    /**
     * Checks if the Turso/libSQL PHP extension is already installed.
     *
     * @return string|false The trimmed output of the shell command if the extension is installed, false otherwise.
     */
    public function checkIsAlreadyExists(): string|false
    {
        if ($this->checkIsWindows()) {
            $searchLibsql = shell_exec('php -m | findstr libsql');
            return $searchLibsql ? trim($searchLibsql) : false;
        } else {
            $searchLibsql = shell_exec('php -m | grep libsql');
            return $searchLibsql ? trim($searchLibsql) : false;
        }
    }

    /**
     * Checks if the php.ini file is present in the environment.
     *
     * @throws \Exception if the php.ini file is not found
     * @return void
     */
    private function checkIsPhpIniExists(): void
    {
        if (empty($this->configIni['loaded_configuration_file'])) {
            $message = <<<ERROR_PHP_INI
            [ERROR]

            You don't have PHP install globaly in your environment
            Turso/libSQL Client PHP lookup php.ini file and it's not found
            ERROR_PHP_INI;
            error($message);
            exit;
        }
    }

    /**
     * Check if the destination directory exists and perform necessary actions based on the existence and content of the directory.
     *
     * @param bool $isUpdateCommand (optional) Flag indicating whether the function is called in the context of an update command. Default is false.
     * @return void
     */
    private function checkIsDestinationExists($isUpdateCommand = false): void
    {
        $metadataFile = "{$this->home}" . DIRECTORY_SEPARATOR . ".tpi-metadata" . DIRECTORY_SEPARATOR . "metadata.json";
        if (!file_exists($metadataFile)) {
            $destination = $this->destination;
        } else {
            $metadata = json_decode(file_get_contents($metadataFile), true);
            $destination = $metadata['destination'] ?? $this->destination;
        }
        $is_dir_exists = false;

        // Normalize the destination path (convert to the right separator for the OS)
        $destination = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destination);

        // Check if the destination is a valid directory
        if (is_dir($destination)) {
            $is_dir_exists = true;

            $search_string = "libsql";

            // Open the directory and search for files containing 'libsql'
            $dir = opendir($destination);
            if ($dir) {
                $found = false;
                while (($file = readdir($dir)) !== false) {
                    if (strpos($file, $search_string) !== false) {
                        $found = true;
                        break;
                    }
                }
                closedir($dir);

                if ($found) {
                    info('Turso Client is Ready!');
                    if ($isUpdateCommand === false) {
                        die();  // Exit the script if no update command is needed
                    }
                } else {
                    info('Extension is not found!');
                    if (!$is_dir_exists) {
                        info("Creating directory at {$destination}");
                        // Create the directory (works for both Unix and Windows)
                        $this->createDirectory($destination);
                    }
                }
            } else {
                error("Failed to open directory {$destination}");
                exit;
            }
        } else {
            info("Creating directory at {$destination}");
            // Create the directory if it does not exist
            $this->createDirectory($destination);
        }
    }

    /**
     * Creates a directory. This function works for both Unix and Windows.
     *
     * @param string $destination The directory path.
     */
    private function createDirectory(string $destination): void
    {
        // Use the correct command based on the operating system
        $command = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'mkdir "' . $destination . '"' : "mkdir -p {$destination}";

        // Execute the shell command to create the directory
        shell_exec($command);
    }

    /**
     * Asks the user for permission to install the Turso client extension in their PHP environment.
     * The function displays a brief message explaining the installation process and prompts the user to accept or decline.
     * If the user declines, the function terminates the script with a farewell message.
     *
     * @return void
     */
    private function askInstallPermission(): void
    {
        $warn = "This script will " . ($this->checkIsWindows() ? null : "ask your sudo password to ") . "modify your php.ini file:";
        $brief = <<<BRIEF_MESSAGE
        Turso need to install the client extension in your PHP environment.
        {$warn}

        BRIEF_MESSAGE;
        echo $brief . PHP_EOL;
        $confirmed = confirm(
            label: 'Do you accept the permission?',
            default: true,
            yes: 'Yes',
            no: 'No',
            hint: 'The terms must be accepted to continue.'
        );

        if (!$confirmed) {
            info("Ok.. no problem, see you later!");
            exit;
        }
    }

    /**
     * Asks for write permission from the user and updates the php.ini file if necessary.
     *
     * @param bool $update Whether to update the php.ini file. Defaults to false.
     * @return void
     */
    private function askWritePermission($update = false): void
    {
        $moduleFile = $this->moduleFile;
        $phpIni = $this->phpIni;

        if ($update === false) {
            if ($this->checkIsWindows()) {
                file_put_contents($phpIni, "\n$moduleFile", FILE_APPEND);
            } else {
                $hasSudo = shell_exec('sudo -v');
                if ($hasSudo) {
                    $command = "sudo -S bash -c 'echo \"$moduleFile\" >> $phpIni'";
                    shell_exec($command);
                } else {
                    echo "Type your : ";
                    $command = "sudo -S bash -c 'echo \"$moduleFile\" >> $phpIni' && sudo -k";
                    shell_exec($command);
                }
            }
        }
        $this->sayThankYou();
    }

    /**
     * Retrieves and parses the PHP configuration ini file.
     *
     * @return array An array containing the parsed configuration ini file.
     */
    private function getConfigIni(): array
    {
        $phpIniFile = shell_exec('php --ini');
        $lines = explode("\n", $phpIniFile);
        $lines = array_slice($lines, 0, 3);

        $configIni = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                $configIni[$this->slugify($key)] = $value;
            }
        }

        return $configIni;
    }

    /**
     * Prepares the OS binary based on the operating system and architecture.
     *
     * This function determines the correct binary archive for the given OS and architecture.
     * It sets the extensionArchive property accordingly.
     *
     * @throws \Exception
     * @return void
     */
    private function getOsBinaryPreparation(): void
    {
        $os = $this->os;
        $arch = $this->arch;
        $selectedPhpVersion = $this->selectedPhpVersion;

        switch (strtolower($os)) {
            case 'darwin':
                switch ($arch) {
                    case "x86_64":
                        $this->extensionArchive = "php-{$selectedPhpVersion}-x86_64-apple-darwin";
                        break;
                    case "arm64":
                        $this->extensionArchive = "php-{$selectedPhpVersion}-aarch64-apple-darwin";
                        break;
                    default:
                        echo "Unsupported architecture: {$arch} for Darwin\n";
                        exit;
                }
                break;
            case 'linux':
                switch ($arch) {
                    case "x86_64":
                        $this->extensionArchive = "php-{$selectedPhpVersion}-x86_64-unknown-linux-gnu";
                        break;
                    default:
                        echo "Unsupported architecture: {$arch} for Linux\n";
                        exit;
                }
                break;
            case str_contains($os, 'windows'):
                switch ($arch) {
                    case "x86_64":
                    case "AMD64":
                        $this->extensionArchive = "php-{$selectedPhpVersion}-x86_64-pc-windows-msvc";
                        break;
                    default:
                        echo "Unsupported architecture: {$arch} for msvc\n";
                        exit;
                }
                break;
            default:
                error("Unsupported operating system: {$os}");
                exit;
        }
    }

    /**
     * Generates a slug from a given string by replacing non-alphanumeric characters, 
     * transliterating non-ASCII characters, removing unwanted characters, trimming, 
     * removing duplicate hyphens, and lowercasing the string.
     *
     * @param string $text The input string to be converted into a slug.
     * @return string The generated slug.
     */
    private function slugify(string $text): string
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '_', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^_\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~_+~', '_', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n_a';
        }

        return $text;
    }

    /**
     * Retrieves the asset releases from the repository.
     *
     * This function uses a spinner to indicate the progress of the operation.
     * It initializes a cURL session to fetch the release metadata, decodes the JSON response,
     * and extracts the assets from the response.
     *
     * @throws \Exception if a cURL error occurs or if the JSON decode fails
     * @throws \Exception if no assets are found in the release metadata
     * @return array|null|\Exception the assets from the release metadata or an exception if an error occurs
     */
    private function getAssetReleases(): array|null|\Exception
    {
        $gettingRelease = spin(function () {
            $response = Http::withUserAgent('darkterminal')->get($this->repo);

            if ($response->failed()) {
                throw new \Exception('Failed to get the latest release metadata: ' . $response->status());
            }

            $release = $response->json();
            $assets = $release['assets'] ?? null;
            if ($assets === null) {
                throw new \Exception('No assets found in release metadata.');
            }

            return $assets;
        }, 'Get the lastest version of the extension...');

        return $gettingRelease;
    }

    /**
     * Downloads and extracts the binary for the Turso/libSQL Client PHP.
     *
     * @param bool $update Whether to update the existing installation.
     * @throws \Exception If there is an error during the download or extraction process.
     * @return void
     */
    private function downloadAndExtractBinary($update = false): void
    {
        $this->checkIsDestinationExists($update);
        $this->getOsBinaryPreparation();
        $assets = $this->getAssetReleases();
        $download_url = null;

        $output_file = spin(function () use ($assets, $download_url) {

            foreach ($assets as $asset) {
                if (strpos($asset['name'], $this->extensionArchive) !== false) {
                    $download_url = $asset['browser_download_url'];
                }
            }

            if ($download_url === null) {
                warning("Download URL is not found!");
                exit;
            }

            $output_file = basename($download_url);
            $response = Http::retry(3, 100)->sink($output_file)->get($download_url);

            if ($response->status() !== 200) {
                error("Failed to download the extension. Status code: " . $response->status());
                exit;
            }

            return $output_file;
        }, 'Downloading the extesion...');

        spin(function () use ($output_file, $update) {

            $metadata = $this->getMetadataFile();
            $destination = $metadata['destination'] ?? $this->destination;
            $this->extractAndMoveFiles($output_file, $update);
            if (!$update) {
                $this->createMetadataFile();
            }

            $message = <<<SETTING_MESSAGE
            [INFO]

            Downloaded release asset to $output_file
            Turso/libSQL Client PHP is downloaded!
            store at {$destination}
            SETTING_MESSAGE;
            info($message);
            echo "\n\n";

            return $output_file;
        }, 'Extract and move the extension...');

        $this->askWritePermission($update);
    }

    private function createMetadataFile(): void
    {
        $homeDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->destination);
        $metadataDir = "{$homeDir}" . DIRECTORY_SEPARATOR . ".tpi-metadata";
        $metadataFile = "{$metadataDir}" . DIRECTORY_SEPARATOR . "metadata.json";

        if (!is_dir($metadataDir)) {
            mkdir($metadataDir);
            touch($metadataFile);
        }

        $metadata = json_encode([
            'destination' => $this->destination,
            'ini_file' => $this->phpIni
        ]);
        file_put_contents($metadataFile, $metadata);
    }

    private function getMetadataFile(): array|null
    {
        $metadataFile = "{$this->destination}" . DIRECTORY_SEPARATOR . ".tpi-metadata" . DIRECTORY_SEPARATOR . "metadata.json";

        if (!file_exists($metadataFile)) {
            return null;
        }

        $metadata = json_decode(file_get_contents($metadataFile), true);
        return $metadata;
    }

    private function extractAndMoveFiles(string $output_file, bool $update): void
    {
        // Normalize paths for Windows compatibility
        $output_file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $output_file);
        if (!$update) {
            $destination = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->destination);
        } else {
            $metadata = $this->getMetadataFile();
            $destination = $metadata['destination'] ?? $this->destination;
        }

        // Extract the file based on the platform (Windows uses .zip, Unix uses .tar.gz)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // For Windows: Use PowerShell to extract a .zip file
            if (pathinfo($output_file, PATHINFO_EXTENSION) === 'zip') {
                $this->extractZipFile($output_file, $destination);
            } else {
                error("Unsupported file type for Windows. Only .zip files are supported.");
                return;
            }
        } else {
            // For Unix-based systems: Use tar to extract .tar.gz files
            if (pathinfo($output_file, PATHINFO_EXTENSION) === 'gz') {
                shell_exec("tar -xzf $output_file -C $destination");
                $directoryName = str_replace('.tar.gz', '', basename($output_file));
                $outDir = $destination . DIRECTORY_SEPARATOR . $directoryName;
                shell_exec("mv $outDir/* $destination/");
                shell_exec("rm -rf $outDir");
            } else {
                error("Unsupported file type for Unix. Only .tar.gz files are supported.");
                return;
            }
        }

        // For both Windows and Unix, we assume the archive is extracted directly into the destination folder
        // Clean up by removing the downloaded archive file
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // For Windows: Use del to remove the .zip file
            shell_exec("del $output_file");
        } else {
            // For Unix-based systems: Use rm to remove the .tar.gz file
            shell_exec("rm $output_file");
        }
    }

    /**
     * Extracts a .zip file to the specified destination.
     *
     * @param string $output_file The path to the .zip file.
     * @param string $destination The destination directory to extract the contents.
     * @return bool True on success, false on failure.
     */
    private function extractZipFile($output_file, $destination): bool
    {
        // Ensure that the destination directory exists
        if (!is_dir($destination)) {
            // Try to create the destination directory if it doesn't exist
            if (!mkdir($destination, 0755, true)) {
                error("Failed to create directory: $destination");
                return false;
            }
        }

        // Initialize ZipArchive class
        $zip = new ZipArchive();

        // Open the .zip file
        if ($zip->open($output_file) === TRUE) {
            // Extract the contents to the destination directory
            if ($zip->extractTo($destination)) {
                // Close the zip file
                $zip->close();
                $fileinfo = pathinfo($output_file);
                $outDir = $destination . DIRECTORY_SEPARATOR . $fileinfo['filename'];
                $this->moveContentsOneLevelUp($outDir);
                rmdir($outDir);
                unlink($output_file);
                return true;
            } else {
                error("Failed to extract the .zip file to $destination");
                $zip->close();
                return false;
            }
        } else {
            error("Failed to open the .zip file: $output_file");
            return false;
        }
    }

    public function moveContentsOneLevelUp(string $sourceDir)
    {
        // Ensure the source directory exists
        if (!is_dir($sourceDir)) {
            error("Source directory does not exist: $sourceDir");
            return;
        }

        // Get the parent directory of the source directory
        $parentDir = dirname($sourceDir);

        // Open the source directory
        $sourceDirHandle = opendir($sourceDir);
        if ($sourceDirHandle === false) {
            error("Failed to open source directory: $sourceDir");
            return;
        }

        // Iterate over the contents of the source directory
        while (($file = readdir($sourceDirHandle)) !== false) {
            // Skip '.' and '..'
            if ($file == '.' || $file == '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $destinationPath = $parentDir . DIRECTORY_SEPARATOR . $file;

            // Check if the item is a directory or a file
            if (is_dir($sourcePath)) {
                // If it's a directory, recursively move it
                if (!is_dir($destinationPath)) {
                    mkdir($destinationPath, 0755, true); // Create the directory in the parent folder
                }
                // Move the directory contents
                $this->moveContentsOneLevelUp($sourcePath); // Recursively move subdirectories
                rmdir($sourcePath); // Remove the now-empty source directory
            } else {
                // If it's a file, move it
                rename($sourcePath, $destinationPath); // Move the file
            }
        }

        // Close the source directory handle
        closedir($sourceDirHandle);
    }

    /**
     * Displays a thank you message after a successful installation.
     *
     * This function is used to display a thank you message to the user after a successful installation of Turso Client PHP.
     * It includes instructions on how to get extension class autocompletion in VSCode Settings.
     *
     * @return void
     */
    private function sayThankYou(): void
    {
        $destination = $this->destination;
        $finish_message = <<<FINISH_MESSAGE

        TURSO CLIENT PHP SUCCESSFULLY INSTALLED!
        To get extension class autocompletion you need to modify your IDE Settings
        in this case VSCode Settings:
        - Open your VSCode setting (cmd/ctrl+,) then search "intelephense.stubs"
        - add this: {$destination} value on the lists
        Thank you for using Turso Database!
        FINISH_MESSAGE;
        info($finish_message);
    }

    /**
     * Displays the system information that meets the requirements.
     *
     * @return void
     */
    private function displayInfo(): void
    {
        $message = <<<DISPLAY_INFO
        Here your detail system information that meet our requirements:

        DISPLAY_INFO;
        echo $message . PHP_EOL;
        table(
            ['Detector', 'Result'],
            [
                ['Operating System', $this->os],
                ['Architecture', $this->arch],
                ['PHP Version', $this->currentVersion . " / " . PHP_VERSION],
                ['Home Directory', $this->home],
                ['PHP INI Location', $this->phpIni]
            ],
        );
    }
}
