<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\File;
use Turso\PHP\Installer\Services\Installation\Platforms\LinuxInstaller;
use Turso\PHP\Installer\ValueObjects\Asset;

class ObservableLinuxInstaller extends LinuxInstaller
{
    public function useClient(Client $client): void
    {
        $this->client = $client;
    }

    public function resolvedPhpIni(): string
    {
        return $this->getPhpIni();
    }

    public function resolvedExtensionDirectory(): string
    {
        return $this->extensionDirectory();
    }
}

beforeEach(function () {
    $this->originalHome = getenv('HOME');
    $this->originalServerHome = $_SERVER['HOME'] ?? null;
    $this->testHome = sys_get_temp_dir() . '/turso-home-' . bin2hex(random_bytes(6));

    mkdir($this->testHome . '/.config', 0755, true);

    // get_user_homedir() reads getenv('HOME'); LinuxInstaller's constructor
    // reads $_SERVER['HOME']. Both must point at the isolated directory.
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

test('plain installation dir and user home resolve from the current HOME', function () {
    expect(get_user_homedir())->toBe($this->testHome)
        ->and(get_plain_installation_dir())->toBe($this->testHome . '/.config/turso-client-php');
});

test('versioned installation dir includes the current PHP version', function () {
    expect(get_version_installation_dir())
        ->toBe($this->testHome . '/turso-client-php/' . get_current_php_version());
});

test('an installation directory without metadata is not treated as installed', function () {
    $dir = get_plain_installation_dir();
    mkdir($dir, 0755, true);
    $installer = new LinuxInstaller();

    expect(is_dir($dir))->toBeTrue()
        ->and($installer->checkIfAlreadyInstalled())->toBeFalse();
});

test('a metadata record marks the installation as present', function () {
    $dir = get_plain_installation_dir();
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/metadata.json', json_encode(['version' => get_current_php_version()]));
    $installer = new LinuxInstaller();

    expect($installer->checkIfAlreadyInstalled())->toBeTrue();
});

test('update restores php.ini and extension directory from metadata before contacting the release API', function () {
    $installer = new ObservableLinuxInstaller();

    $phpIni = $this->testHome . '/chosen/php.ini';
    $extensionDir = $this->testHome . '/custom-extension-dir';
    mkdir(dirname($phpIni), 0755, true);
    file_put_contents($phpIni, "; test ini\n");

    $metadataDir = get_plain_installation_dir();
    mkdir($metadataDir, 0755, true);
    file_put_contents($metadataDir . '/metadata.json', json_encode([
        'version' => get_current_php_version(),
        'nts' => false,
        'stable' => true,
        'extension_directory' => $extensionDir,
        'php_ini' => $phpIni,
        'cert_store_location' => $this->testHome . '/certs',
    ]));

    $releaseUrl = 'https://github.com/tursodatabase/turso-client-php/releases/download/v9.9.9/release_metadata.json';

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('get')->once()->with(RELEASE_METADATA_LATEST_URL)
        ->andReturn(new Response(200, [], json_encode(['tag_name' => 'v9.9.9'])));
    $client->shouldReceive('get')->once()->with($releaseUrl)
        ->andReturn(new Response(200, [], json_encode([
            'name' => 'v9.9.9',
            'html_url' => 'https://github.com/tursodatabase/turso-client-php/releases/tag/v9.9.9',
            'assets' => [],
        ])));

    $installer->useClient($client);

    try {
        $installer->update();
        $this->fail('Expected update() to fail when no matching extension asset exists.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('No extension asset matching');
    }

    expect($installer->resolvedPhpIni())->toBe($phpIni)
        ->and($installer->resolvedExtensionDirectory())->toBe($extensionDir);
});

test('asset temp directory follows the Sail HOME branch only when Sail is present', function () {
    $asset = Asset::from([
        'name' => 'libsql-php-test.tar.gz',
        'browser_download_url' => 'https://example.test/libsql-php-test.tar.gz',
    ]);

    $property = new ReflectionProperty(Asset::class, 'download_path');
    $expected = File::exists('/home/sail') ? $this->testHome . '/.tmp' : sys_get_temp_dir();

    expect($property->getValue($asset))->toBe($expected);
});
