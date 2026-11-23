<?php

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/*
 * Plan 006 characterization tests.
 *
 * Commands that call exit() are exercised as real subprocesses so a guard
 * never kills the test runner. Subprocess tests belong to the "helpers" group
 * and are skipped when ext-libsql is absent — the token store is a real
 * \LibSQL database, so faking it would prove nothing.
 */

function helper_cli(array $args, string $home): Process
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/turso-php-installer', ...$args]);
    $process->setEnv([
        'HOME' => $home,
        'PATH' => getenv('PATH'),
        'PHPRC' => getenv('PHPRC') ?: '',
    ]);
    $process->setTimeout(60);
    $process->run();

    return $process;
}

function helper_home(): string
{
    $home = sys_get_temp_dir() . '/turso-helpers-' . bin2hex(random_bytes(6));
    mkdir($home . '/.config/turso-client-php', 0755, true);

    return $home;
}

function helper_mark_installed(string $home): void
{
    file_put_contents($home . '/.config/turso-client-php/metadata.json', json_encode([
        'version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        'nts' => true,
        'stable' => true,
        'extension_directory' => $home . '/ext',
        'php_ini' => $home . '/php.ini',
        'cert_store_location' => $home . '/certs',
    ]));
}

function helper_requires_extension(): void
{
    // The extension registers as libsql_php (see issue #36); the class is the
    // trustworthy signal, not the module name.
    if (!class_exists('LibSQL')) {
        test()->markTestSkipped('ext-libsql is not loaded; run the helpers CI job.');
    }
}

/* ---------------------------------------------------------------- definitions */

test('token command definitions match the documented surface', function () {
    $commands = Artisan::all();

    $create = $commands['token:create']->getDefinition();
    expect($create->hasArgument('db-name'))->toBeTrue()
        ->and($create->getOption('expire')->getDefault())->toBe('7');

    $show = $commands['token:show']->getDefinition();
    foreach (['fat', 'roa', 'pkp', 'pkb'] as $flag) {
        expect($show->hasOption($flag))->toBeTrue();
    }

    $delete = $commands['token:delete']->getDefinition();
    expect($delete->getArgument('db-name')->isRequired())->toBeFalse()
        ->and($delete->hasOption('all'))->toBeTrue()
        ->and($delete->hasOption('force'))->toBeTrue();

    expect($commands['token:list']->getDefinition()->getArguments())->toBe([]);
});

test('server command definitions match the documented surface', function () {
    $commands = Artisan::all();

    $ca = $commands['server:ca-cert-create']->getDefinition();
    expect($ca->getArgument('name')->getDefault())->toBe('ca')
        ->and($ca->getOption('expiry')->getDefault())->toBe('30');

    $peer = $commands['server:ca-peer-cert-create']->getDefinition();
    expect($peer->getOption('expiry')->getDefault())->toBe('30');

    expect($commands['server:ca-cert-show']->getDefinition()->hasOption('raw'))->toBeTrue();
    expect($commands['server:cert-store-set']->getDefinition()->getArgument('path')->isRequired())->toBeFalse();
    expect($commands['server:ca-cert-delete']->getDefinition()->hasOption('all'))->toBeTrue();
});

test('sqld command definitions match the documented surface', function () {
    $commands = Artisan::all();

    $envNew = $commands['sqld:env-new']->getDefinition();
    expect($envNew->hasOption('variables'))->toBeTrue()
        ->and($envNew->hasOption('force'))->toBeTrue();

    $run = $commands['sqld:server-run']->getDefinition();
    expect($run->getArgument('env-id-or-name')->isRequired())->toBeTrue()
        ->and($run->getArgument('db-name')->isRequired())->toBeTrue()
        ->and($run->getOption('daemon')->getShortcut())->toBe('d');

    expect($commands['sqld:daemon-kill']->getDefinition()->getArgument('daemon-pid')->isRequired())->toBeTrue();
    expect($commands['sqld:open-db']->getDefinition()->getArgument('db-name')->isRequired())->toBeTrue();
});

/* ------------------------------------------------------------- token guards */

test('token commands refuse to run without an installation record', function () {
    helper_requires_extension();

    $home = helper_home();

    foreach (['token:list', 'token:create demo', 'token:show demo'] as $command) {
        $process = helper_cli(explode(' ', $command), $home);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getOutput() . $process->getErrorOutput())->toContain('not installed');
    }

    remove_directory($home);
})->group('helpers');

/* ------------------------------------------------------------ token lifecycle */

test('token create, list, show and delete round-trip against the real store', function () {
    helper_requires_extension();

    $home = helper_home();
    helper_mark_installed($home);

    $create = helper_cli(['token:create', 'demo', '--expire=7'], $home);
    expect($create->getExitCode())->toBe(0);

    $list = helper_cli(['token:list'], $home);
    expect($list->getExitCode())->toBe(0)
        ->and($list->getOutput())->toContain('demo');

    $show = helper_cli(['token:show', 'demo', '--fat'], $home);
    expect($show->getExitCode())->toBe(0);
    $jwt = trim($show->getOutput());
    expect(substr_count($jwt, '.'))->toBe(2);

    $delete = helper_cli(['token:delete', 'demo', '--force'], $home);
    expect($delete->getExitCode())->toBe(0);

    $after = helper_cli(['token:list'], $home);
    expect($after->getOutput())->not->toContain('demo');

    remove_directory($home);
})->group('helpers');

/* --------------------------------------------------------- sqld env validation */

test('sqld env-new rejects malformed variables and the gRPC conflict', function () {
    helper_requires_extension();

    $home = helper_home();

    $missingToken = helper_cli(['sqld:env-new', 'demo', '--variables={"SQLD_NODE":"primary"}'], $home);
    expect($missingToken->getExitCode())->toBe(1)
        ->and($missingToken->getOutput())->toContain('DATABASE_TOKEN_ID');

    $conflict = helper_cli([
        'sqld:env-new', 'demo',
        '--variables={"DATABASE_TOKEN_ID":1,"SQLD_GRPC_LISTEN_ADDR":"127.0.0.1:5001","SQLD_PRIMARY_GRPC_URL":"127.0.0.1:5001"}',
    ], $home);
    expect($conflict->getExitCode())->toBe(1)
        ->and($conflict->getOutput())->toContain('mutually exclusive');

    $badJson = helper_cli(['sqld:env-new', 'demo', '--variables=%%%'], $home);
    expect($badJson->getExitCode())->toBe(1);

    remove_directory($home);
})->group('helpers');

test('sqld env lifecycle and duplicate protection behave as documented', function () {
    helper_requires_extension();

    $home = helper_home();
    helper_mark_installed($home);

    // env-show resolves the referenced token, so the environment must point
    // at a real token id rather than a fabricated one.
    expect(helper_cli(['token:create', 'demo'], $home)->getExitCode())->toBe(0);

    $store = new LibSQL($home . '/.config/turso-client-php/tokens.db');
    $row = $store->query('SELECT id FROM tokens WHERE db_name = ?', ['demo'])->fetchArray(LibSQL::LIBSQL_ASSOC);
    $tokenId = (int) $row[0]['id'];
    expect($tokenId)->toBeGreaterThan(0);

    $create = helper_cli(['sqld:env-new', 'demo', '--variables={"DATABASE_TOKEN_ID":' . $tokenId . ',"SQLD_NODE":"standalone","SQLD_HTTP_LISTEN_ADDR":"127.0.0.1:8080"}'], $home);
    expect($create->getExitCode())->toBe(0);

    $duplicate = helper_cli(['sqld:env-new', 'demo', '--variables={"DATABASE_TOKEN_ID":1}'], $home);
    expect($duplicate->getExitCode())->toBe(1)
        ->and($duplicate->getOutput())->toContain('--force');

    $list = helper_cli(['sqld:env-list'], $home);
    expect($list->getExitCode())->toBe(0)
        ->and($list->getOutput())->toContain('demo');

    $show = helper_cli(['sqld:env-show', 'demo'], $home);
    expect($show->getExitCode())->toBe(0);

    $delete = helper_cli(['sqld:env-delete', 'demo'], $home);
    expect($delete->getExitCode())->toBe(0);

    remove_directory($home);
})->group('helpers');

/* ------------------------------------------------------------ daemon handling */

test('daemon listing and killing degrade cleanly with no daemon file', function () {
    $home = helper_home();

    $list = helper_cli(['sqld:daemon-list'], $home);
    expect($list->getExitCode())->toBe(0)
        ->and($list->getOutput())->toContain('No running sqld daemons found');

    $kill = helper_cli(['sqld:daemon-kill', '999999'], $home);
    expect($kill->getExitCode())->toBe(1);

    remove_directory($home);
})->group('helpers');

/* ------------------------------------------------------------------ cleanup */

function remove_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}
