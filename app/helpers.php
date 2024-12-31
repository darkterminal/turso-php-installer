<?php

use Illuminate\Support\Str;

const UNSTABLE_REPOSITORY = 'https://raw.githubusercontent.com/pandanotabear/turso-client-php/main/release_metadata.json';
const REPOSITORY = 'https://raw.githubusercontent.com/tursodatabase/turso-client-php/main/release_metadata.json';
const USER_AGENT = 'darkterminal';
const VERSION = '2.0.3';
const DS = DIRECTORY_SEPARATOR;

function get_current_php_version(): string
{
    return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
}

function get_version_installation_dir(bool $isStable = true): string
{
    return sprintf(
        '%s%s.turso-client-php%s%s',
        '/home/user',
        DIRECTORY_SEPARATOR,
        $isStable ? '' : DIRECTORY_SEPARATOR . 'unstable',
        DIRECTORY_SEPARATOR . get_current_php_version()
    );
}

function get_plain_installation_dir(): string
{
    return get_user_homedir() . DS . ".turso-client-php";
}

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

function is_windows(): bool
{
    return PHP_OS_FAMILY === 'Windows';
}

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

function sql_file(string $name): string
{
    return file_get_contents(config('database.sql_statements') . DS . $name . '.sql');
}
