<?php

namespace App\Services\Installation;

use App\Contracts\Installer;
use App\ValueObjects\Asset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

abstract class BaseInstaller implements Installer
{
    public const VERSION = '2.0.2';

    protected const REPOSITORY = 'https://raw.githubusercontent.com/tursodatabase/turso-client-php/main/release_metadata.json';

    protected const USER_AGENT = 'darkterminal';

    protected string $php_version = '';

    protected string $home_directory = '';

    protected string $php_ini = '';

    protected bool $unstable = false;

    protected string $arch;

    protected string $os;

    protected string $extension_name;

    protected string $original_extension_name;

    protected string $custom_extension_directory;

    protected bool $non_thread_safe = false;

    public function setUnstable(bool $unstable): void
    {
        $this->unstable = $unstable;
    }

    public function __construct()
    {
        $this->arch = php_uname('m');
        $this->os = strtolower(php_uname('s'));

        $this->extension_name = 'libsql_php.so';
        $this->original_extension_name = 'liblibsql_php.so';
        $this->custom_extension_directory = '';
    }

    public function setPhpIni(string $php_ini): void
    {
        $this->php_ini = $php_ini;
    }

    public function setPhpVersion(string $php_version): void
    {
        $this->php_version = $php_version;
    }

    public function setNonThreadSafe(): void
    {
        $this->non_thread_safe = true;
    }

    public function getPHPVersion(): string
    {
        if ($this->php_version) {
            return $this->php_version;
        }

        return $this->php_version = Str::of(PHP_VERSION)
            ->explode('.')
            ->slice(0, -1)
            ->implode('.'); // e.g., 8.0, 8.1
    }

    public function setExtensionDir(string $extension_dir): void
    {
        $this->custom_extension_directory = $extension_dir;
    }

    protected function getPhpIni(): string
    {
        if ($this->php_ini) {
            return $this->php_ini;
        }

        $detectedIni = Str::of(shell_exec('php --ini'))
            ->explode("\n")
            ->filter(fn ($line) => str_contains($line, '/php.ini') || str_contains($line, '\php.ini'))
            ->first();

        if (blank($detectedIni)) {
            throw new RuntimeException(
                "PHP is not installed globally in your environment.\n".
                'Turso/libSQL Client PHP attempted to locate a php.ini file but none was found.'
            );
        }

        return trim(Str::of($detectedIni)
            ->explode(':')
            ->filter()
            ->last());
    }

    protected function extensionDirectory(): string
    {
        if ($this->custom_extension_directory) {
            return $this->custom_extension_directory;
        }

        if ($this->unstable) {
            return collect([$this->home_directory, '.turso-client-php', 'unstable', $this->getPHPVersion()])
                ->implode(DIRECTORY_SEPARATOR);
        }

        return collect([$this->home_directory, '.turso-client-php', $this->getPHPVersion()])
            ->implode(DIRECTORY_SEPARATOR);
    }

    protected function ensureLibSQLExtensionDirectoryExists(): void
    {
        if (! File::exists($this->extensionDirectory())) {
            File::makeDirectory($this->extensionDirectory(), 0755, true);
        }
    }

    protected function assetVersion(bool $shouldIncludeTS): string
    {
        $os = match ($this->os) {
            'darwin' => 'apple-darwin',
            'linux' => 'unknown-linux-gnu',
            'windows' => 'pc-windows-msvc',
            default => $this->os,
        };

        $arch = match ($this->arch) {
            'x86_64', 'AMD64' => 'x86_64',
            'arm64' => 'aarch64',
            default => $this->arch,
        };

        if ($shouldIncludeTS) {

            $threadSafety = $this->non_thread_safe ? 'nts' : 'ts';

            return collect([$this->getPHPVersion(), $threadSafety, $arch, $os])
                ->filter()
                ->implode('-');
        }

        return collect([$this->getPHPVersion(), $arch, $os])
            ->filter()
            ->implode('-');
    }

    protected function removeExtensionFromPhpIni(): void
    {
        spin(function () {
            $ini = $this->getPhpIni();

            $contents = LazyCollection::make(function () use ($ini) {
                $file = fopen($ini, 'r');
                while (! feof($file)) {
                    yield fgets($file);
                }
                fclose($file);
            });

            $contentWithoutLibSQL = $contents->reject(fn ($line) => Str::contains($line, $this->extension_name));
            File::put($ini, $contentWithoutLibSQL->join(''));
        }, 'Removing libsql from php.ini file...');

        info('  ✅ php.ini updated');
    }

    protected function getRepository()
    {
        if ($this->unstable) {
            return 'https://raw.githubusercontent.com/pandanotabear/turso-client-php/main/release_metadata.json';
        }

        return self::REPOSITORY;
    }

    protected function downloadExtension(): void
    {
        $path = $this->extensionDirectory();

        $request = spin(
            message: 'Getting the latest version of the extension...',
            callback: fn () => Http::withUserAgent(self::USER_AGENT)->get($this->getRepository())
        );

        [$major, $minor] = str($request->json('name'))
            ->explode('.')
            ->filter(fn ($part) => is_numeric($part))
            ->collect()
            ->map(fn ($part) => (int) $part)
            ->flatten();

        $assets = $request->json('assets');

        $asset = collect($assets)
            ->map(fn ($asset) => Asset::from($asset))
            ->first(function (Asset $asset) use ($major, $minor) {
                return Str::of($asset->name)->contains($this->assetVersion(shouldIncludeTS: $major === 1 && $minor >= 4));
            });

        if ($asset === null) {
            throw new RuntimeException('The extension for your PHP version is not available. Please open an issue on the repository.');
        }

        info(sprintf('  ✅ Found latest release for %s %s %s', $this->os, $this->arch, $this->getPHPVersion()));

        $asset->download();

        info('  ✅ Extension downloaded');

        $asset->extract($path, [
            $this->original_extension_name,
        ]);

        File::move(
            $path.DIRECTORY_SEPARATOR.$this->original_extension_name,
            $path.DIRECTORY_SEPARATOR.$this->extension_name
        );
    }

    protected function updatePhpIni(): void
    {
        spin(function () {
            $ini = $this->getPhpIni();
            $path = $this->extensionDirectory();

            $contents = LazyCollection::make(function () use ($ini) {
                $file = fopen($ini, 'r');
                while (! feof($file)) {
                    yield fgets($file);
                }
                fclose($file);
            });

            if ($contents->contains(fn ($line) => Str::contains($line, $this->extension_name))) {
                return;
            }

            File::append($ini, PHP_EOL.'extension='.$path.DIRECTORY_SEPARATOR.$this->extension_name.PHP_EOL);
        }, 'Updating php.ini file...');

        info('  ✅ php.ini updated');
    }

    protected function removeExtensionFiles(): void
    {
        $path = $this->extensionDirectory();
        File::delete($path.DIRECTORY_SEPARATOR.$this->extension_name);
        File::deleteDirectory($path);

        info('  ✅ libsql extension removed');
    }

    public function update(): void
    {
        $this->getPhpIni();
        $this->ensureLibSQLExtensionDirectoryExists();
        $this->downloadExtension();
        $this->updatePhpIni();
        $this->info();
    }

    public function uninstall(): void
    {
        info(sprintf('  ✅ Found php ini at %s', $this->getPhpIni()));
        $this->removeExtensionFromPhpIni();
        $this->removeExtensionFiles();
    }

    public function install(): void
    {
        info(sprintf('  ✅ Found php ini at %s', $this->getPhpIni()));
        $this->ensureLibSQLExtensionDirectoryExists();
        $this->downloadExtension();
        $this->updatePhpIni();
        $this->info();
    }

    public function info()
    {
        table(headers: [
            'OS',
            'Architecture',
            'PHP Version',
            'Extension Path',
            'php.ini used',
        ], rows: [[
            $this->os,
            $this->arch,
            $this->getPHPVersion(),
            $this->extensionDirectory(),
            $this->getPhpIni(),
        ]]);
    }
}
