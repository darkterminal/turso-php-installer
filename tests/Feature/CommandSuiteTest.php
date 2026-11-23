<?php

use Illuminate\Support\Facades\Artisan;

test('the application boots and registers the core command suite', function () {
    $this->artisan('list')->assertExitCode(0);

    $registered = array_keys(Artisan::all());

    expect($registered)
        ->toContain('install', 'update', 'uninstall', 'version')
        ->toContain('token:create', 'token:list', 'token:show', 'token:delete')
        ->toContain('sqld:env-new', 'sqld:env-list', 'sqld:env-show', 'sqld:env-edit', 'sqld:env-delete')
        ->toContain('sqld:server-run', 'sqld:open-db', 'sqld:daemon-list', 'sqld:daemon-kill')
        ->toContain('server:check', 'server:cert-store-get', 'server:cert-store-set', 'server:ca-cert-create', 'server:ca-cert-list', 'server:ca-cert-show', 'server:ca-peer-cert-create', 'server:ca-cert-delete')
        ->toContain('test');
});

test('the version command succeeds and prints the installer banner', function () {
    $exit = Artisan::call('version');

    // A shallow CI checkout has no tags for `git describe`, so Laravel Zero
    // reports "unreleased". Accept both shapes; never pin a concrete version.
    expect($exit)->toBe(0)
        ->and(Artisan::output())->toMatch('/Turso libSQL Installer \(version: (?:\d+\.\d+\.\d+|unreleased)\)/');
});
