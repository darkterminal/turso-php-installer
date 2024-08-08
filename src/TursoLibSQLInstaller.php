<?php

namespace Darkterminal\TursoLibSQLInstaller;

use Exception;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class TursoLibSQLInstaller
{
    public const VERSION = '1.0.0';
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

    public function help(): void
    {
        echo "Turso libSQL Installer (version: " . self::VERSION . ")\n";
        $commands = <<<COMMANDS
        commands:
            - help                          Display all command
            - install                       Install libSQL Extension for PHP
            - update                        Update libSQL Extension for PHP
            - uninstall                     Uninstall libSQL Extension for PHP
            - add:tenancy-for-laravel       Add tenancy for laravel package + turso driver laravel (Laravel Project only) Experimental
        COMMANDS;
        echo $commands . PHP_EOL;
    }

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

    public function update(): void
    {
        $isFound = $this->checkIsAlreadyExists();
        if ($isFound) {
            $this->downloadAndExtractBinary(true);
            exit;
        }
        error("You doesn't have Turso libSQL Extension installed before.");
    }

    public function uninstall(): void
    {
        $isFound = $this->checkIsAlreadyExists();
        if ($isFound) {
            echo "To uninstall the extension, turso need your sudo permission.\n";
            $escapedModuleFile = str_replace('/', '\/', $this->moduleFile);

            shell_exec("sudo -k");
            echo "Type your ";
            $command = "sudo -S bash -c \"sed -i '/$escapedModuleFile/d' {$this->phpIni}\" && sudo -k";
            shell_exec($command);

            $this->removeDirectory($this->destination);

            $message = <<<UNINSTALL_MESSAGE
            [INFO]

            Removed extension line from {$this->phpIni}
            echo "THANK YOU FOR USING TURSO libSQL Extension for PHP
            UNINSTALL_MESSAGE;
            info($message);
            exit;
        }
        error("You doesn't have Turso libSQL Extension installed before.");
    }

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

    private function checkIsAlreadyExists(): string|false
    {
        $searchLibsql = shell_exec('php -m | grep libsql');
        return $searchLibsql ? trim($searchLibsql) : false;
    }

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

    private function checkIsDestinationExists($isUpdateCommand = false): void
    {
        $is_dir_exists = false;

        if (is_dir($this->destination)) {
            $is_dir_exists = true;

            $search_string = "libsql";

            if (is_dir($this->destination)) {
                $dir = opendir($this->destination);
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
                            info("Creating directory at {$this->destination}");
                            shell_exec("mkdir {$this->destination}");
                        }
                    }
                } else {
                    error("Failed to open directory {$this->destination}");
                    exit;
                }
            } else {
                error("{$this->destination} is not a valid directory");
                exit;
            }
        } else {
            info("Creating directory at {$this->destination}");
            shell_exec("mkdir {$this->destination}");
        }
    }

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

    private function askWritePermission($update = false): void
    {
        if ($update === false) {
            shell_exec("sudo -k");
            echo "Type your ";
            $command = "sudo -S bash -c 'echo \"$this->moduleFile\" >> $this->phpIni' && sudo -k";
            shell_exec($command);
        }
        $this->sayThankYou();
    }

    private function getConfigIni(): array
    {
        $phpIniFile = shell_exec('php --ini');
        $lines = explode("\n", $phpIniFile);
        $lines = array_slice($lines, 0, 3);

        $configIni = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = array_map('trim', explode(':', $line, 2));
                $configIni[$this->slugify($key)] = $value;
            }
        }

        return $configIni;
    }

    private function getOsBinaryPreparation(): void
    {
        switch ($this->os) {
            case 'darwin':
                if ($this->arch == "x86_64") {
                    $this->extensionArchive = "php-{$this->selectedPhpVersion}-x86_64-apple-darwin";
                } else if ($this->arch == "arm64") {
                    $this->extensionArchive = "php-{$this->selectedPhpVersion}-aarch64-apple-darwin";
                } else {
                    echo "Unsupported architecture: {$this->arch} for Darwin\n";
                    exit;
                }
                break;
            case 'linux':
                if ($this->arch == "x86_64") {
                    $this->extensionArchive = "php-{$this->selectedPhpVersion}-x86_64-unknown-linux-gnu";
                } else {
                    echo "Unsupported architecture: {$this->arch} for Linux\n";
                    exit;
                }
                break;
            default:
                echo "Unsupported OS: {$this->os}\n";
                exit;
        }
    }

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

    private function getAssetReleases(): array|null|Exception
    {
        $gettingRelease = spin(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->repo);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: darkterminal"]);
            $releaseMetadata = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            $release = json_decode($releaseMetadata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON decode error: ' . json_last_error_msg());
            }

            $assets = $release['assets'] ?? null;
            if ($assets === null) {
                throw new Exception('No assets found in release metadata.');
            }

            return $assets;
        }, 'Get the lastest version of the extension...');

        return $gettingRelease;
    }

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
                echo "Download URL is not found!\n";
                exit;
            }

            $output_file = basename($download_url);
            shell_exec("curl -L $download_url -o $output_file");

            return $output_file;
        }, 'Downloading the extesion...');
        
        $output_file = spin(function () use ($output_file) {

            shell_exec("tar -xzf $output_file");

            $directory = str_replace('.tar.gz', '', $output_file);

            shell_exec("mv $directory/* {$this->destination}/");

            shell_exec("rm $output_file");
            rmdir($directory);

            $message = <<<SETTING_MESSAGE
            [INFO]

            Downloaded release asset to $output_file
            Turso/libSQL Client PHP is downloaded!
            store at {$this->destination}
            SETTING_MESSAGE;
            info($message);
            echo "\n\n";

            return $output_file;
        }, 'Extract and move the extension...');
        $this->askWritePermission($update);
    }

    private function sayThankYou(): void
    {
        $finish_message = <<<FINISH_MESSAGE

        TURSO CLIENT PHP SUCCESSFULLY INSTALLED!
        To get extension class autocompletion you need to modify your IDE Settings
        in this case VSCode Settings:
        - Open your VSCode setting (cmd/ctrl+,) then search "intelephense.stubs"
        - add this: {$this->destination} value on the lists
        Thank you for using Turso Database!
        FINISH_MESSAGE;
        info($finish_message);
    }

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
