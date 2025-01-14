<?php

use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * The URL to the gist that contains the extension's metadata.
 *
 * @link https://gist.github.com/darkterminal/99c0cc406968b50143539620dac2095e
 */
const GIST_URL = 'https://api.github.com/gists/99c0cc406968b50143539620dac2095e';

/**
 * The user agent string sent with requests to the repository.
 *
 * @var string
 */
const USER_AGENT = 'darkterminal';

/**
 * The current version of the package.
 *
 * @var string
 */
const VERSION = '2.0.3';

/**
 * The directory separator.
 *
 * @var string
 */
const DS = DIRECTORY_SEPARATOR;

/**
 * Create a new Faker generator instance.
 *
 * This function creates and returns a Faker generator instance, which can be used
 * to generate fake data for testing purposes. The generator can be customized to 
 * generate data in a specific locale by passing the desired locale string as a parameter.
 *
 * @param string|null $locale The locale to use for generating fake data. Defaults to 'en_US'.
 * @return Generator A Faker generator instance.
 */
function faker(string|null $locale = null): Generator
{
    $defaultLocale = $locale ?? 'en_US';
    $faker = Factory::create($defaultLocale);
    return $faker;
}

/**
 * Get the current PHP version.
 *
 * @return string
 */
function get_current_php_version(): string
{
    return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
}

/**
 * Get the full path of the Turso database directory.
 *
 * This function returns the path of the Turso database directory.
 * The Turso database directory is a directory located in the Turso
 * client PHP installation directory. The directory is used to store
 * the databases created by the Turso client PHP package.
 *
 * @return string The full path of the Turso database directory.
 */
function sqld_database_path(): string
{
    $ext_dir = get_plain_installation_dir();
    $db_dir = $ext_dir . DS . 'databases';
    if (!is_dir($db_dir)) {
        mkdir($db_dir);
    }
    return $db_dir;
}

/**
 * Get the full path of the versioned installation directory.
 *
 * @param bool $isStable
 * @return string
 */
function get_version_installation_dir(bool $isStable = true): string
{
    return collect([
        get_user_homedir(),
        'turso-client-php',
        $isStable ? null : 'unstable',
        get_current_php_version(),
    ])->filter()->implode(DS);
}

/**
 * Get the base directory path for Turso client PHP installation.
 *
 * This function returns the path to the installation directory
 * located in the user's home directory without any versioning.
 *
 * @return string The path to the installation directory.
 */

function get_plain_installation_dir(): string
{
    return collect([
        get_user_homedir(),
        '.config',
        'turso-client-php'
    ])->implode(DS);
}

/**
 * Get the path to the user's home directory.
 *
 * @return string The path to the user's home directory.
 */
function get_user_homedir(): string
{
    return trim(is_windows() ? getenv('USERPROFILE') : getenv('HOME'));
}

/**
 * Get the operating system architecture.
 *
 * @return string
 */
function get_os_arch(): string
{
    return php_uname('m');
}

/**
 * Get the name of the operating system.
 *
 * @return string
 */
function get_os_name(): string
{
    $osname = collect(
        Str::of(php_uname('s'))
            ->lower()
            ->explode('-')
            ->filter()
    )->first();

    return str_contains(strtolower($osname),'nt') ? Str::of($osname)->replace('nt', '')->trim() : $osname;
}

/**
 * Gets the path to the active php.ini file.
 *
 * @return string The path to the php.ini file.
 *
 * @throws \RuntimeException If no php.ini file is found.
 */
function get_php_ini_file(): string
{
    $detactedPhpIni = Str::of(shell_exec('php --ini'))
        ->explode("\n")
        ->filter(fn($line) => str_contains($line, '/php.ini') || str_contains($line, '\php.ini'))
        ->first();

    if (blank($detactedPhpIni)) {
        throw new RuntimeException(
            "PHP is not installed globally in your environment.\n
            Turso/libSQL Client PHP attempted to locate a php.ini file but none was found."
        );
    }

    return trim(Str::of($detactedPhpIni)
        ->explode(':')
        ->filter()
        ->last());
}

/**
 * Run a command with superuser privileges.
 *
 * This function will execute the provided command with superuser privileges using the
 * `sudo` command. If the command fails to execute, the function will throw a
 * `RuntimeException`.
 *
 * @param string $command The command to execute.
 *
 * @throws \RuntimeException If the command fails to execute.
 */
function sudo_shell_exec(string $command): void
{
    $hasSudo = shell_exec('sudo -v');
    if ($hasSudo) {
        $command = "sudo -S bash -c '$command'";
        shell_exec($command);
    } else {
        $command = "sudo -S bash -c '$command' && sudo -k";
        shell_exec($command);
    }
}

/**
 * Checks if the current operating system is Windows.
 *
 * @return bool True if the OS is Windows, false otherwise.
 */
function is_windows(): bool
{
    return PHP_OS_FAMILY === 'Windows';
}

/**
 * Checks that the required PHP extensions and the OpenSSL command-line tool are installed and available.
 *
 * This function is called by the Turso client PHP installer to ensure that
 * the required dependencies are installed before attempting to install the
 * extension.
 *
 * @throws \RuntimeException If any of the required extensions are not installed
 *     or enabled, or if the OpenSSL command-line tool is not installed or
 *     not in your PATH.
 */
function check_generator_requirements(): void
{
    $requiredExtensions = ['openssl', 'sodium'];

    $missingExtensions = array_filter($requiredExtensions, fn($ext) => !extension_loaded($ext));

    if (!empty($missingExtensions)) {
        throw new RuntimeException("Error: The following PHP extensions are not installed or enabled: " . implode(', ', $missingExtensions));
    }

    $opensslPath = match (true) {
        stripos(PHP_OS, 'WIN') !== false => shell_exec('where openssl'),
        default => shell_exec('which openssl'),
    };

    if (!$opensslPath) {
        throw new RuntimeException("Error: OpenSSL command-line tool is not installed or not in your PATH.");
    }
}

/**
 * Reads the contents of a SQL file from the SQL statements directory.
 *
 * @param string $name The name of the SQL file to read.
 * @return string The contents of the SQL file.
 */
function sql_file(string $name): string
{
    return file_get_contents(config('app.sql_statements') . DS . $name . '.sql');
}

/**
 * Retrieves a value from the global metadata file.
 *
 * @param string $key The key to retrieve from the metadata file.
 * @return string The value associated with the given key.
 * @throws \RuntimeException If the key is not found in the metadata file.
 */
function get_global_metadata(string $key): string
{
    $metadata = json_decode(file_get_contents(config('app.metadata')), true);
    if (!isset($metadata[$key])) {
        throw new RuntimeException("Unknown metadata key: $key");
    }
    return $metadata[$key];
}

function check_libsql_installed(): bool
{
    if (is_windows()) {
        return !empty(shell_exec('php -m | findstr libsql'));
    }

    return !empty(shell_exec('php -m | grep libsql'));
}

/**
 * Generates a clickable link for CLI (compatible with ANSI-supported terminals).
 *
 * @param string $url  The URL to be linked.
 * @param string $text The text to display as the clickable link.
 * @return string      The formatted clickable link.
 */
function clickable_link(string $url, string $text): string
{
    return "\033]8;;$url\033\\$text\033]8;;\033\\\n";
}

/**
 * Checks whether a given path is an absolute path.
 *
 * This function checks whether the given path is an absolute path by
 * checking if it starts with a forward slash or if it has a drive letter.
 *
 * @param string $path The path to check.
 * @return bool True if the path is an absolute path, false otherwise.
 */
function is_absolute_path(string $path): bool
{
    if ($path[0] === '/') {
        return true;
    }
    
    if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
        return true;
    }

    return false;
}
