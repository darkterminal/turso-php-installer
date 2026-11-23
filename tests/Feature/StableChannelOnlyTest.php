<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Turso\PHP\Installer\Services\Installation\Platforms\LinuxInstaller;

class ChannelObservableInstaller extends LinuxInstaller
{
    public function useClient(Client $client): void
    {
        $this->client = $client;
    }

    public function repositoryUrl(): string
    {
        return $this->getRepository();
    }
}

beforeEach(function () {
    $this->originalHome = getenv('HOME');
    $this->originalServerHome = $_SERVER['HOME'] ?? null;
    $this->testHome = sys_get_temp_dir() . '/turso-channel-' . bin2hex(random_bytes(6));

    mkdir($this->testHome . '/.config', 0755, true);

    putenv('HOME=' . $this->testHome);
    $_SERVER['HOME'] = $this->testHome;
});

afterEach(function () {
    putenv('HOME=' . $this->originalHome);

    if ($this->originalServerHome === null) {
        unset($_SERVER['HOME']);
    } else {
        $_SERVER['HOME'] = $this->originalServerHome;
    }

    File::deleteDirectory($this->testHome);
    Mockery::close();
});

test('the install command no longer exposes an unstable option', function () {
    $definition = Artisan::all()['install']->getDefinition();

    expect($definition->hasOption('unstable'))->toBeFalse()
        ->and($definition->hasOption('thread-safe'))->toBeTrue()
        ->and($definition->hasOption('php-version'))->toBeTrue();
});

test('the versioned installation directory has no unstable segment', function () {
    expect(get_version_installation_dir())
        ->toBe($this->testHome . '/turso-client-php/' . get_current_php_version())
        ->and(get_version_installation_dir())->not->toContain('unstable');
});

test('release metadata always resolves to the stable asset', function () {
    $installer = new ChannelObservableInstaller();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')->once()->with(RELEASE_METADATA_LATEST_URL)
        ->andReturn(new Response(200, [], json_encode(['tag_name' => 'v9.9.9'])));

    $installer->useClient($client);

    expect($installer->repositoryUrl())
        ->toBe('https://github.com/tursodatabase/turso-client-php/releases/download/v9.9.9/release_metadata.json');
});

test('new installation metadata always records the stable channel', function () {
    $installer = new LinuxInstaller();

    $phpIni = $this->testHome . '/chosen/php.ini';
    $extensionDir = $this->testHome . '/custom-extension-dir';
    mkdir(dirname($phpIni), 0755, true);
    file_put_contents($phpIni, "; test ini\n");

    $installer->setPhpIni($phpIni);
    $installer->setExtensionDir($extensionDir);
    $installer->rememberInstallationDirectory();

    $metadata = json_decode(file_get_contents(get_plain_installation_dir() . '/metadata.json'), true);

    expect($metadata['stable'])->toBeTrue()
        ->and($metadata['php_ini'])->toBe($phpIni);
});

test('update refuses a legacy unstable installation instead of migrating silently', function () {
    $metadataDir = get_plain_installation_dir();
    mkdir($metadataDir, 0755, true);
    file_put_contents($metadataDir . '/metadata.json', json_encode([
        'version' => get_current_php_version(),
        'nts' => false,
        'stable' => false,
        'extension_directory' => $this->testHome . '/turso-client-php/unstable/' . get_current_php_version(),
        'php_ini' => $this->testHome . '/chosen/php.ini',
        'cert_store_location' => $this->testHome . '/certs',
    ]));

    $installer = new LinuxInstaller();

    expect(fn () => $installer->update())
        ->toThrow(RuntimeException::class, 'unstable channel, which is no longer supported');
});
