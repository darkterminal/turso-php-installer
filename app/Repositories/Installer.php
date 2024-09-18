<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class Installer
{
    public const VERSION = '2.0.1';
    private string $repo;
    private string $os;
    private string $arch;
    private string $home;
    private string $currentVersion;
    private string $selectedPhpVersion;
    private string $binaryName;
    private string $destination;
    private string $herdPath;
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
        $this->home = trim(shell_exec('echo $HOME'));
        $this->currentVersion = implode('.', array_slice(explode('.', PHP_VERSION), 0, -1));
        $this->selectedPhpVersion = $this->currentVersion;
        $this->binaryName = "liblibsql_php.so";
        $this->destination = "$this->home/.turso-client-php";
        $this->herdPath = "$this->home/Library/Application Support/Herd";
        $this->configIni = $this->getConfigIni();
        $this->isAlreadyExists = $this->checkIsAlreadyExists();
        $this->moduleFile = "extension=$this->destination/$this->binaryName";
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
    public function install(bool $autoConfirm = false, string|null $specifiedVersion = null): void
    {
        $this->checkIsWindows();
        $this->checkIsLaravelHerd();

        if (!$autoConfirm) {
            $this->checkIfAlreadyExists();
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

            if ($confirmUninstall) {
                $escapedModuleFile = str_replace('/', '\/', $this->moduleFile);

                $phpIni = $this->phpIni;
                echo "Type your ";
                $command = "sudo -S bash -c \"sed -i '/$escapedModuleFile/d' {$phpIni}\" && sudo -k";
                shell_exec($command);

                $this->removeDirectory($this->destination);

                info(
                    message: "Removed extension line from {$phpIni}\nTHANK YOU FOR USING TURSO libSQL Extension for PHP"
                );
            } else {
                info(message: "Uninstallation cancelled.");
            }
        } else {
            error(message: "You don't have Turso libSQL Extension installed before.");
        }
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir The path to the directory to be removed.
     * @return bool True if the directory is successfully removed, false otherwise.
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = "$dir/$file";
            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        return rmdir($dir);
    }

    /**
     * Checks if the current operating system is Windows and displays a warning message if true.
     *
     * @return void
     */
    private function checkIsWindows(): void
    {
        $isWindows = PHP_OS_FAMILY === 'Windows';

        if ($isWindows) {
            $message = <<<WINDOWS_ERR
            [WARNING]

            Sorry, Turso Installer is only support for Linux and MacOS.
            
            You are using Windows, you try our alternative using Dev Containers
            visit: https://github.com/darkterminal/turso-docker-php
            
            Thank you!

            WINDOWS_ERR;
            warning($message);
            exit;
        }
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
     * Checks if the Laravel Herd is being used and displays a warning message if it is.
     *
     * @throws \Exception if Laravel Herd is being used
     * @return void
     */
    private function checkIsLaravelHerd(): void
    {
        if (is_dir($this->herdPath)) {
            $message = <<<HERD_WARNING
            [WARNING]

            Your're using Laravel Herd
            Sorry, Laravel Herd is not supported yet.
            HERD_WARNING;
            warning($message);
            exit;
        }
    }

    /**
     * Checks if the Turso/libSQL Client PHP is already installed and configured.
     *
     * @return void
     */
    private function checkIfAlreadyExists(): void
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
    private function checkIsAlreadyExists(): string|false
    {
        $searchLibsql = shell_exec('php -m | grep libsql');
        return $searchLibsql ? trim($searchLibsql) : false;
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
        $is_dir_exists = false;
        $destination = $this->destination;

        if (is_dir($destination)) {
            $is_dir_exists = true;

            $search_string = "libsql";

            if (is_dir($destination)) {
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
                            die();
                        }
                    } else {
                        info('Extension is not found!');
                        if (!$is_dir_exists) {
                            info("Creating directory at {$destination}");
                            shell_exec("mkdir {$destination}");
                        }
                    }
                } else {
                    error("Failed to open directory {$destination}");
                    exit;
                }
            } else {
                error("{$destination} is not a valid directory");
                exit;
            }
        } else {
            info("Creating directory at {$destination}");
            shell_exec("mkdir {$destination}");
        }
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
        $brief = <<<BRIEF_MESSAGE
        Turso need to install the client extension in your PHP environment.
        This script will ask your sudo password to modify your php.ini file:

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
        $isDocker = file_exists('/.dockerenv');
        $moduleFile = $this->moduleFile;
        $phpIni = $this->phpIni;
        
        if ($update === false) {
            if ($isDocker) {
                $command = "bash -c 'echo \"$moduleFile\" >> $phpIni'";
                shell_exec($command);
            } else {
                shell_exec("sudo -k");
                echo "Type your : ";
                $command = "sudo -S bash -c 'echo \"$moduleFile\" >> $phpIni' && sudo -k";
                shell_exec($command);
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

        switch ($os) {
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

        spin(function () use ($output_file) {

            shell_exec("tar -xzf $output_file");

            $directory = str_replace('.tar.gz', '', $output_file);

            $destination = $this->destination;
            shell_exec("mv $directory/* {$destination}/");

            shell_exec("rm $output_file");
            rmdir($directory);

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
