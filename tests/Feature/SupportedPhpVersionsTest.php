<?php

use Turso\PHP\Installer\Services\Installation\Platforms\LinuxInstaller;

test('the supported version list drops PHP 8.1', function () {
    expect(SUPPORTED_PHP_VERSIONS)->toBe(['8.2', '8.3', '8.4', '8.5'])
        ->and(SUPPORTED_PHP_VERSIONS)->not->toContain('8.1');
});

test('the interactive picker offers exactly the supported versions', function () {
    $options = php_version_options();

    expect(array_keys($options))->toBe(SUPPORTED_PHP_VERSIONS)
        ->and($options)->not->toHaveKey('8.1')
        ->and($options['8.3'])->toBe('PHP 8.3');
});

test('an explicit PHP 8.1 target is rejected with an actionable message', function () {
    $installer = new LinuxInstaller();

    expect(fn () => $installer->setPhpVersion('8.1'))
        ->toThrow(InvalidArgumentException::class, 'PHP 8.1 is not a supported install target');
});

test('the rejection message names the supported versions', function () {
    $installer = new LinuxInstaller();

    try {
        $installer->setPhpVersion('8.1');
        $this->fail('Expected setPhpVersion() to reject PHP 8.1.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('Supported versions: 8.2, 8.3, 8.4, 8.5')
            ->toContain('PHP 8.1 support was dropped');
    }
});

test('unsupported versions outside the dropped range are rejected too', function () {
    $installer = new LinuxInstaller();

    expect(fn () => $installer->setPhpVersion('7.4'))
        ->toThrow(InvalidArgumentException::class, 'not a supported install target');
});

test('a supported target is accepted and used verbatim', function () {
    $installer = new LinuxInstaller();
    $installer->setPhpVersion('8.3');

    expect($installer->getPHPVersion())->toBe('8.3');
});
