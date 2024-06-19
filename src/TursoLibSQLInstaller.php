<?php

namespace Darkterminal\TursoLibSQLInstaller;

use Exception;

class TursoLibSQLInstaller
{
    public const VERSION = '1.0.0';
    private string $repo;
    private string $os;
    private string $arch;
    private string $home;
    private string $currentVersion;
    private string $minimalVersion;
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
        $this->minimalVersion = "8.0";
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
            - help          Display all command
            - install       Install libSQL Extension for PHP
            - update        Update libSQL Extension for PHP
            - uninstall     Uninstall libSQL Extension for PHP
        COMMANDS;
        echo $commands . PHP_EOL;
    }

    public function install(): void
    {
        $this->checkIsWindows();
        $this->checkIsLaravelHerd();
        $this->checkIfAlreadyExists();
        $this->checkPhpVersion();
        $this->checkIsPhpIniExists();
        $this->checkFunctionRequirements();
        $this->askInstallPermission();
        $this->displayInfo();
        $this->downloadAndExtractBinary();
    }

    public function update(): void
    {
        $this->downloadAndExtractBinary(true);
    }

    public function uninstall(): void
    {
        shell_exec("sudo -k");
        $command = "sudo sed -i '/". str_replace('/', '\/', $this->moduleFile) ."/d' {$this->phpIni}' && sudo -k";
        shell_exec($command);
        $this->removeDirectory($this->destination);
        echo "Removed extension line from {$this->phpIni}\n";
        echo "THANK YOU FOR USING TURSO libSQL Extension for PHP\n";
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
            Sorry, Turso Database is only support for Linux and MacOS.
            
            You are using Windows, you try our alternative using Dev Containers
            visit: https://github.com/darkterminal/turso-docker-php
            
            Thank you!
            WINDOWS_ERR;
            echo $message . PHP_EOL;
            exit(1);
        }
    }

    private function checkPhpVersion(): void
    {
        $phpVersionChecker = version_compare(PHP_VERSION, $this->minimalVersion, '>=');
        if (!$phpVersionChecker) {
            echo "Oops! Your PHP version environment does not meet the requirements.\n";
            echo "Need a minimal PHP {$this->minimalVersion} installed on your environment.\n";
            exit(1);
        }
    }

    private function checkFunctionRequirements(): void
    {
        if (!function_exists('shell_exec') && !function_exists('curl_version')) {
            $message = <<<ERR_FUNC_NOT_FOUND
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
            echo $message . PHP_EOL;
            exit(1);
        }
    }

    private function checkIsLaravelHerd(): void
    {
        if (is_dir($this->herdPath)) {
            echo "Your're using Laravel Herd\n";
            echo "Sorry, Laravel Herd is not supported yet.\n";
            exit(0);
        }
    }

    private function checkIfAlreadyExists(): void
    {
        if (!empty($this->isAlreadyExists)) {
            echo "Turso Client PHP is already installed and configured!\n";
            exit(0);
        }
    }

    private function checkIsAlreadyExists(): ?string
    {
        $searchLibsql = shell_exec('php -m | grep libsql');
        return $searchLibsql ? trim($searchLibsql) : null;
    }

    private function checkIsPhpIniExists(): void
    {
        if (empty($this->configIni['loaded_configuration_file'])) {
            echo "You don't have PHP install globaly in your environment\n";
            echo "Turso Client PHP lookup php.ini file and it's not found\n";
            exit(1);
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
                        echo "Turso Client is Ready!\n";
                        if ($isUpdateCommand === false) {
                            die();
                        }
                    } else {
                        echo "Extension is not found!\n";
                        if (!$is_dir_exists) {
                            shell_exec("mkdir {$this->destination}");
                        }
                    }
                } else {
                    echo "Failed to open directory {$this->destination}\n";
                    exit(1);
                }
            } else {
                echo "{$this->destination} is not a valid directory\n";
                exit(1);
            }
        } else {
            shell_exec("mkdir {$this->destination}");
        }
    }

    private function askInstallPermission(): void
    {
        echo "Turso need to install the client extension in your PHP environment.\n";
        echo "This script will ask your sudo password to modify your php.ini file:\n";
        $answer = readline("Are you ok? [y/N]: ");

        if (strtolower(trim($answer)) !== 'y') {
            echo "Ok... no problem, see you later!\n";
            exit(0);
        }
    }

    private function askWritePermission($update = false): void
    {
        if ($update === false) {
            shell_exec("sudo -k");

            echo "Please enter your sudo password: ";
            shell_exec('stty -echo');
            $sudoPassword = trim(fgets(STDIN));
            shell_exec('stty echo');
            echo "\n\n";

            $command = "echo '$sudoPassword' | sudo -S bash -c 'echo \"$this->moduleFile\" >> $this->phpIni' && sudo -k";

            shell_exec($command);
        }
        echo "\n\n";
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
                    $this->extensionArchive = "php-{$this->currentVersion}-x86_64-apple-darwin";
                } else if ($this->arch == "arm64") {
                    $this->extensionArchive = "php-{$this->currentVersion}-aarch64-apple-darwin";
                } else {
                    echo "Unsupported architecture: {$this->arch} for Darwin\n";
                    exit(1);
                }
                break;
            case 'linux':
                if ($this->arch == "x86_64") {
                    $this->extensionArchive = "php-{$this->currentVersion}-x86_64-unknown-linux-gnu";
                } else {
                    echo "Unsupported architecture: {$this->arch} for Linux\n";
                    exit(1);
                }
                break;
            default:
                echo "Unsupported OS: {$this->os}\n";
                exit(1);
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

    private function getAssetReleases(): array|null
    {
        try {
            echo "Downloading...\n";
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
        } catch (Exception $e) {
            throw new Exception('Error: ' . $e->getMessage());
        }
    }

    private function downloadAndExtractBinary($update = false): void
    {
        $this->checkIsDestinationExists($update);
        $this->getOsBinaryPreparation();
        $assets = $this->getAssetReleases();
        $download_url = null;

        foreach ($assets as $asset) {
            if (strpos($asset['name'], $this->extensionArchive) !== false) {
                $download_url = $asset['browser_download_url'];
            }
        }

        if ($download_url === null) {
            echo "Download URL is not found!\n";
            exit(1);
        }

        $output_file = basename($download_url);
        shell_exec("curl -L $download_url -o $output_file");

        sleep(2);

        shell_exec("tar -xzf $output_file");

        sleep(2);

        $directory = str_replace('.tar.gz', '', $output_file);

        shell_exec("mv $directory/* {$this->destination}/");

        shell_exec("rm $output_file");
        rmdir($directory);

        echo "Downloaded release asset to $output_file\n";
        echo "Your extension is already downloaded!\n";
        echo "store at {$this->destination}.\n";
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
        echo $finish_message . PHP_EOL;
    }

    private function displayInfo(): void
    {
        echo "Detected OS           : $this->os\n";
        echo "Detected Architecture : $this->arch\n";
        echo "PHP Version           : $this->currentVersion / " . PHP_VERSION . "\n";
        echo "Home Directory        : $this->home\n";
        echo "PHP INI Location      : $this->phpIni\n";
    }
}
