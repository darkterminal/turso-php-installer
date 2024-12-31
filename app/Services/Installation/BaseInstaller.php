<?php

namespace App\Services\Installation;

use App\Contracts\Installer;
use App\Traits\UseRemember;
use App\ValueObjects\Asset;
use Illuminate\Support\Collection;
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
    use UseRemember;
    
    protected const REPOSITORY = 'https://raw.githubusercontent.com/tursodatabase/turso-client-php/main/release_metadata.json';
    
    protected const USER_AGENT = 'darkterminal';

    public const VERSION = '2.0.2';
    
    protected string $arch;

    protected string $custom_extension_directory;

    protected string $extension_name;

    protected string $home_directory = '';

    protected bool $non_thread_safe = false;

    protected string $os;

    protected string $original_extension_name;

    protected string $original_stubs_name;

    protected string $php_version = '';

    protected string $php_ini = '';

    protected string $stubs_name;
    
    protected bool $unstable = false;

    public function __construct()
    {
        $this->arch = php_uname('m');
        $this->os = collect(explode(' ', strtolower(php_uname('s'))))->first();

        $this->original_extension_name = 'liblibsql_php.so';
        $this->extension_name = 'libsql_php.so';
        $this->original_stubs_name = 'libsql_php_extension.stubs.php';
        $this->stubs_name = 'libsql_php.stubs.php';
        $this->custom_extension_directory = '';
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

    public function checkIfAlreadyInstalled(): bool
    {
        return File::exists($this->home_directory . DIRECTORY_SEPARATOR . '.turso-client-php');
    }

    protected function downloadExtension(): void
    {
        $path = $this->extensionDirectory();

        $this->rememberInstallationDirectory();

        $request = spin(
            message: 'Getting the latest version of the extension...',
            callback: fn() => Http::withUserAgent(self::USER_AGENT)->get($this->getRepository())
        );

        [$major, $minor] = str($request->json('name'))
            ->explode('.')
            ->filter(fn($part) => is_numeric($part))
            ->collect()
            ->map(fn($part) => (int) $part)
            ->flatten();

        $assets = $request->json('assets');

        $asset = collect($assets)
            ->map(fn($asset) => Asset::from($asset))
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
            $this->original_stubs_name
        ]);

        if ($this->os !== 'windows') {
            File::move(
                $path . DIRECTORY_SEPARATOR . $this->original_extension_name,
                $path . DIRECTORY_SEPARATOR . $this->extension_name
            );
            File::move(
                $path . DIRECTORY_SEPARATOR . $this->original_stubs_name,
                $path . DIRECTORY_SEPARATOR . $this->stubs_name
            );
        }

        $asset->removeArchive();
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
        if (!File::exists($this->extensionDirectory())) {
            File::makeDirectory($this->extensionDirectory(), 0755, true);
        }
    }

    protected function getUnstable(): bool
    {
        return $this->unstable;
    }

    protected function getNonThreadSafe(): bool
    {
        return $this->non_thread_safe;
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

    protected function getPhpIni(): string
    {
        if ($this->php_ini) {
            return $this->php_ini;
        }

        $detectedIni = Str::of(shell_exec('php --ini'))
            ->explode("\n")
            ->filter(fn($line) => str_contains($line, '/php.ini') || str_contains($line, '\php.ini'))
            ->first();

        if (blank($detectedIni)) {
            throw new RuntimeException(
                "PHP is not installed globally in your environment.\n
                Turso/libSQL Client PHP attempted to locate a php.ini file but none was found."
            );
        }

        return trim(Str::of($detectedIni)
            ->explode(':')
            ->filter()
            ->last());
    }

    protected function getMetadataLocationFile(): string
    {
        return collect([$this->home_directory, '.turso-client-php', 'metadata.json'])->implode(DIRECTORY_SEPARATOR);
    }

    protected function getRepository()
    {
        if ($this->unstable) {
            return 'https://raw.githubusercontent.com/pandanotabear/turso-client-php/main/release_metadata.json';
        }

        return self::REPOSITORY;
    }

    protected function getExtensionDirToRemember(): string
    {
        $path = $this->extensionDirectory();
        return Str::contains($path, 'turso-client-php') ? collect([$this->home_directory, '.turso-client-php'])->implode(DIRECTORY_SEPARATOR) : $path;
    }

    protected function getExtensionString(): string
    {
        return PHP_EOL . 'extension=' . $this->extensionDirectory() . DIRECTORY_SEPARATOR . $this->extension_name . PHP_EOL;
    }

    /**
     * Gets the metadata of the currently installed extension.
     * 
     * Here is the keys:
     * - version
     * - nts
     * - stable
     * - extension_directory
     * - php_ini
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function getMetadata(): Collection
    {
        $metadata = json_decode(File::get($this->getMetadataLocationFile()), true);
        return collect($metadata);
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
        ], rows: [
            [
                $this->os,
                $this->arch,
                $this->getPHPVersion(),
                $this->extensionDirectory(),
                $this->getPhpIni(),
            ]
        ]);
    }

    protected function removeExtensionFromPhpIni(): void
    {
        if (File::isWritable($this->getPhpIni())) {
            spin(function () {
                $ini = $this->getPhpIni();

                $contents = LazyCollection::make(function () use ($ini) {
                    $file = fopen($ini, 'r');
                    while (!feof($file)) {
                        yield fgets($file);
                    }
                    fclose($file);
                });

                $contentWithoutLibSQL = $contents->reject(fn($line) => Str::contains($line, $this->extension_name));
                File::put($ini, $contentWithoutLibSQL->join(''));
            }, 'Removing libsql from php.ini file...');
        } else {
            $this->removingExtensionFromPhpIniWithSudo();
        }

        info('  ✅ php.ini updated');
    }

    protected function removingExtensionFromPhpIniWithSudo(): void
    {
        $this->runSudoCommand("sed -i '/{$this->extension_name}/d' {$this->getPhpIni()}");
    }

    protected function runSudoCommand(string $command): void
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

    protected function removeExtensionFiles(): void
    {
        $ext_dir = $this->getMetadata()->get('extension_directory');
        if (!File::deleteDirectory($ext_dir)) {
            throw new RuntimeException('Failed to delete extension directory');
        }
        info('  ✅ libsql extension removed');
    }

    public function setUnstable(bool $unstable): void
    {
        $this->unstable = $unstable;
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

    public function setExtensionDir(string $extension_dir): void
    {
        $this->custom_extension_directory = $extension_dir;
    }

    protected function updatePhpIni(): void
    {
        $ini = $this->getPhpIni();

        if (File::isWritable($ini)) {
            $this->removeExtensionFromPhpIni();
            
            spin(function () use ($ini) {
                $contents = LazyCollection::make(function () use ($ini) {
                    $file = fopen($ini, 'r');
                    while (!feof($file)) {
                        yield fgets($file);
                    }
                    fclose($file);
                });

                if ($contents->contains(fn($line) => Str::contains($line, $this->extension_name))) {
                    return;
                }

                File::append($ini, $this->getExtensionString());
            }, 'Updating php.ini file...');
        } else {
            $this->updatingPhpIniWithSudo();
        }
        info('  ✅ php.ini updated');
    }

    protected function updatingPhpIniWithSudo(): void
    {
        $ini = $this->getPhpIni();
        $content = $this->getExtensionString();

        info(" Updating php.ini file...");
        $this->runSudoCommand("sed -i '/{$this->extension_name}/d' {$this->getPhpIni()} && echo \"$content\" >> $ini");
    }

    public function update(): void
    {
        if (File::exists($this->getMetadataLocationFile())) {
            $metadata = $this->getMetadata();

            $this->setUnstable(!$metadata->get('stable'));

            $this->setPhpIni($metadata->get('php_ini'));

            if ($metadata->get('nts')) {
                $this->setNonThreadSafe();
            }

            if (!Str::contains($metadata->get('extension_directory'), 'turso-client-php')) {
                $this->setExtensionDir($metadata->get('extension_directory'));
            }
        }

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
}
