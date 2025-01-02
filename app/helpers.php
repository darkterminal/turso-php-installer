<?php

use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * The URL of the unstable repository.
 *
 * @var string
 */
const UNSTABLE_REPOSITORY = 'https://raw.githubusercontent.com/pandanotabear/turso-client-php/main/release_metadata.json';

/**
 * The URL of the stable repository.
 *
 * @var string
 */
const REPOSITORY = 'https://raw.githubusercontent.com/tursodatabase/turso-client-php/main/release_metadata.json';

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
 * Get the full path of the versioned installation directory.
 *
 * @param bool $isStable
 * @return string
 */
function get_version_installation_dir(bool $isStable = true): string
{
    return sprintf(
        '%s%s.turso-client-php%s%s',
        get_user_homedir(),
        DIRECTORY_SEPARATOR,
        $isStable ? '' : DIRECTORY_SEPARATOR . 'unstable',
        DIRECTORY_SEPARATOR . get_current_php_version()
    );
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
    return get_user_homedir() . DS . ".turso-client-php";
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
    return collect(explode(' ', strtolower(php_uname('s'))))->first();
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
