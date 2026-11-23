<?php

use Turso\PHP\Installer\Services\Installation\BaseInstaller;
use Turso\PHP\Installer\Services\Installation\Platforms\LinuxInstaller;

beforeEach(function () {
    $this->ghToken = getenv('GH_TOKEN');
    $this->githubToken = getenv('GITHUB_TOKEN');

    putenv('GH_TOKEN');
    putenv('GITHUB_TOKEN');
});

afterEach(function () {
    foreach (['GH_TOKEN' => $this->ghToken, 'GITHUB_TOKEN' => $this->githubToken] as $name => $value) {
        if ($value === false) {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
});

test('the user agent is always sent and auth is omitted without a token', function () {
    expect(github_api_headers())->toBe(['User-Agent' => USER_AGENT])
        ->and(github_api_headers())->not->toHaveKey('Authorization');
});

test('GH_TOKEN is used as a bearer token when present', function () {
    putenv('GH_TOKEN=gh-test-token');

    expect(github_api_headers())->toBe([
        'User-Agent' => USER_AGENT,
        'Authorization' => 'Bearer gh-test-token',
    ]);
});

test('GITHUB_TOKEN is used when GH_TOKEN is absent', function () {
    putenv('GITHUB_TOKEN=github-test-token');

    expect(github_api_headers())->toBe([
        'User-Agent' => USER_AGENT,
        'Authorization' => 'Bearer github-test-token',
    ]);
});

test('GH_TOKEN takes precedence over GITHUB_TOKEN', function () {
    putenv('GH_TOKEN=gh-test-token');
    putenv('GITHUB_TOKEN=github-test-token');

    expect(github_api_headers()['Authorization'])->toBe('Bearer gh-test-token');
});

test('an empty token does not add an authorization header', function () {
    putenv('GH_TOKEN=   ');
    putenv('GITHUB_TOKEN=');

    expect(github_api_headers())->toBe(['User-Agent' => USER_AGENT]);
});

test('the installer API client uses the resolved GitHub headers', function () {
    putenv('GH_TOKEN=gh-test-token');

    $installer = new LinuxInstaller();
    $client = (new ReflectionProperty(BaseInstaller::class, 'client'))->getValue($installer);

    expect($client->getConfig('headers'))->toBe(github_api_headers())
        ->and($client->getConfig('headers')['Authorization'])->toBe('Bearer gh-test-token');
});
