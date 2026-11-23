<?php

test('the reported version comes from composer.json', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['version'])->not->toBeEmpty()
        ->and(installer_version())->toBe($composer['version'])
        ->and(config('app.version'))->toBe($composer['version']);
});

test('the version command prints the package version', function () {
    $this->artisan('version')
        ->expectsOutputToContain('Turso libSQL Installer (version: ' . config('app.version') . ')')
        ->assertSuccessful();
});
