# Plan 001: Make install/update behavior testable and add a CI test gate

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving to the next step. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 056da37..HEAD -- app/ tests/ composer.json composer.lock .github/workflows/`
> If any of those files changed since this plan was written, compare the
> "Current state" excerpts against the live code before proceeding; on a
> mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: tests
- **Planned at**: commit `056da37`, 2026-11-23

## Why this matters

Plans 002 and 003 change how the Sail container installs, verifies and updates
the libSQL extension. Today those code paths have **zero behavioral tests**: the
suite has exactly two tests (command registration and the version banner), and
there is no test workflow in CI at all — only `docs.yml` and a weekly Composer
updater. The Sail stub depends on HOME-relative metadata, a metadata-driven
extension directory and a php.ini rewrite; none of that is characterized.
Without a gate here, a later refactor can silently break every Sail user and no
command will turn red. This plan builds the safety net first.

## Current state

- `tests/Feature/CommandSuiteTest.php` (24 lines) — the entire test suite. Two
  tests: `the application boots and registers the core command suite` (lines
  5–17) and `the version command succeeds and prints the installer banner`
  (lines 19–24). The second asserts only `Artisan::output()` contains
  `'Turso libSQL Installer (version:'`.
- `tests/Pest.php` — `uses(Tests\TestCase::class)->in('Feature');`. No other
  setup, no HOME isolation.
- `phpunit.xml.dist` — only a `Feature` testsuite pointing at `tests/Feature`.
- `.github/workflows/` — only `dependency_updater.yml` and `docs.yml`. No test
  workflow.
- `composer.json:36-41` — `require-dev` already includes `laravel/pint:^1.21`,
  `mockery/mockery:^1.6`, `pestphp/pest:^2.36`. No new dependency is needed.
- `app/helpers.php:121-138` — metadata dir is derived live from `getenv('HOME')`:
  `get_plain_installation_dir()` → `<HOME>/.config/turso-client-php`, and
  `get_user_homedir()` → `trim(getenv('HOME'))`.
- `app/Services/Installation/BaseInstaller.php:243-246` —
  `getMetadataLocationFile()` also resolves live from
  `get_plain_installation_dir()`, **not** from cached `config('app.metadata')`.
  This is what makes HOME-isolated tests possible.
- `app/Services/Installation/BaseInstaller.php:94-104` —
  `checkIfAlreadyInstalled()` returns `is_dir($dir) && file_exists(metadata.json)`.
- `app/Services/Installation/BaseInstaller.php:453-477` — `update()` reads
  metadata, restores php.ini/extension dir, then calls `downloadExtension()`,
  `updatePhpIni()`, `verifyExtensionEnabled()`.
- `app/Services/Installation/BaseInstaller.php:51-63` — constructor creates
  `protected Client $client` (Guzzle) directly. Tests can replace it via a small
  subclass or reflection; no container binding exists.
- `app/ValueObjects/Asset.php:36-44` — `from()` picks a `getenv('HOME') . '/.tmp'`
  temp dir when `/home/sail` exists, otherwise `sys_get_temp_dir()`.
- `composer validate --strict --no-check-publish --no-check-version` currently
  reports: valid, but "The lock file is not up to date with the latest changes
  in composer.json". `--no-check-version` is required because the repo keeps a
  `version` field in composer.json, and `--strict` turns that warning into exit
  code 1; **do not remove that field**, `build.sh:21` reads `.version` from
  composer.json to compute release versions.

Repo conventions to honor:
- Commit messages are conventional: `fix(docs): …`, `docs: …`, `chore: …`,
  `release: …` (from `git log`). Use `test(...)` / `ci(...)` prefixes here.
- Tests are Pest functions in `tests/Feature/`, using `test('…', function () { … })`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Install deps | `composer install` | exit 0 |
| Tests | `vendor/bin/pest --colors=never` | all pass |
| Composer sanity | `composer validate --strict --no-check-publish --no-check-version` | "valid", no lock-file error |
| Git state | `git status --short -b` | only in-scope files changed |

**Do NOT use `vendor/bin/pint --test` as a gate.** Verified on 2026-11-23: it
reports failures in nearly every source file because the repo does not follow
Pint's default ruleset. Adding it to CI would make the first run red and is out
of scope. Pint may be mentioned in docs as optional, never as a required check.

## Scope

**In scope** (the only files you should modify):
- `.github/workflows/tests.yml` (create)
- `composer.lock` (refresh hash only)
- `tests/Feature/CommandSuiteTest.php` (extend) or new files under `tests/Feature/` —
  e.g. `tests/Feature/InstallationStateTest.php`, `tests/Feature/AssetTest.php`
- `tests/Pest.php` (only if HOME isolation needs a shared `beforeEach`)

**Out of scope** (do NOT touch, even though they look related):
- Any file under `app/` — this plan adds tests around current behavior, it does
  not change behavior. If a test exposes a bug, record it in the report; do not
  fix it here.
- `composer.json` — do not remove the `version` field, do not bump dependencies.
- `build.sh`, `plans/README.md` beyond the status row.
- The Sail docs or stubs (plans 002–005).

## Git workflow

- Follow `plans/WORKFLOW.md` (standing workflow). Create branch
  `plan/001-verification-baseline`; never work directly on `main`.
- Issue `Plan 001 — ...` must exist BEFORE the branch is created. Put
  `Refs #<issue>` in every commit body and `Closes #<issue>` in the PR body,
  together with this plan path.
- Commit per logical unit with conventional messages, e.g.
  `test: characterize HOME-resolved metadata and update path` and
  `ci: add Pest test workflow`.
- Open a PR against `main`, run every verification gate, double-check done
  criteria and CI, then squash-merge only when clean. Update the status row in
  `plans/README.md` in a small follow-up commit on `main`.

## Steps

### Step 1: Refresh the lock file hash

Run `composer update --lock --no-install --no-interaction`.

**Verify**: `composer validate --strict --no-check-publish --no-check-version` → output contains
"valid" and no longer contains "The lock file is not up to date". The remaining
"version field is present" warning is acceptable and expected. `git diff --stat
composer.lock` should show a small metadata/hash change, not a mass upgrade.

If `--lock` still leaves the file stale, STOP and report — a full
`composer update` is not authorized in this plan.

### Step 2: Add the CI test workflow

Create `.github/workflows/tests.yml` matching the existing style (PHP 8.3 in
`dependency_updater.yml`; use 8.3 for consistency). Trigger on push and PR to
`main`, with paths covering `app/**`, `tests/**`, `composer.*`,
`phpunit.xml.dist`, and this workflow. Steps: `actions/checkout@v6`,
`shivammathur/setup-php@v2` with `php-version: '8.3'` and `coverage: none`,
`composer install --no-interaction --prefer-dist`, then
`vendor/bin/pest --colors=never`. Optionally add
`composer validate --strict --no-check-publish --no-check-version` as a second check; both must be
non-blocking only if they are already non-blocking locally — they are not, so
make both blocking.

**Verify**: `ruby -e "require 'yaml'; YAML.load_file('.github/workflows/tests.yml')"`
→ exit 0 (Ruby is available on GitHub runners; if absent locally, at minimum run
`git diff --check` and visually confirm indentation). The local Pest command from
the table still passes.

### Step 3: Characterize HOME-resolved metadata helpers

Add tests that isolate `HOME` to a temp directory. Approach: in a `beforeEach`,
create `sys_get_temp_dir() . '/turso-test-' . uniqid()`, `putenv('HOME=' . $path)`
and register an `afterEach` that restores the original HOME and deletes the tree.
`putenv` works because `get_user_homedir()` reads `getenv('HOME')` live
(`app/helpers.php:135-138`).

Cover:
- `get_plain_installation_dir()` equals `<temp HOME>/.config/turso-client-php`.
- `get_version_installation_dir()` includes the chosen PHP version segment.
- `checkIfAlreadyInstalled()` is `false` when the directory exists but
  `metadata.json` is absent (the regression described at
  `BaseInstaller.php:98-103`), and `true` once a minimal valid metadata file is
  written.
- `getMetadataLocationFile()` is not reachable publicly; exercise it through
  `checkIfAlreadyInstalled()` as above rather than reflection.

Model the test style after `tests/Feature/CommandSuiteTest.php`.

**Verify**: `vendor/bin/pest --colors=never --filter=InstallationState` → all
new tests pass and the two original tests still pass.

### Step 4: Characterize the update path without network

Write a test that instantiates a concrete installer, sets a temp extension dir
and temp php.ini, writes the metadata file, replaces the protected `$client`
with a Mockery Guzzle client returning a synthetic release payload, and calls
`update()`. The payload shape must match `BaseInstaller::downloadExtension()`
(`app/Services/Installation/BaseInstaller.php:111-166`): a top-level `name`
containing a SemVer, an `assets` array with a `browser_download_url` and a
`name` matching the expected `assetVersion(...)` token, plus `html_url`.

If building a valid synthetic archive proves too brittle, do NOT fake a download:
instead assert only the deterministic parts — that `update()` restores
`php_ini` and `extension_directory` from metadata before reaching the network,
and that a network failure surfaces as a `RuntimeException` rather than being
swallowed. That still characterizes the Sail-critical behavior.

Also add a test for `Asset::from()`:
- with `/home/sail` absent, temp path is `sys_get_temp_dir()`;
- when emulating Sail (`/home/sail` exists), temp path is `<HOME>/.tmp` and the
  directory is created with mode 0755. Creating `/home/sail` is a host-level
  side effect — if the test environment is not root, skip this case with
  `test()->markTestSkipped(...)` and record the gap in the report rather than
  forcing it.

**Verify**: `vendor/bin/pest --colors=never` → all tests pass, including the two
original ones and the new cases. No test may make a real network call.

### Step 5: Characterize the version banner contract

Extend the existing banner test to assert the exact shape the Sail script used
to parse (documented here so the executor does not need the audit): bare
`turso-php-installer` prints a summary line containing
`turso-php-installer  2.2.2` on this checkout, while `version` prints
`Turso libSQL Installer (version: 2.2.2)`. Add an assertion that the banner
contains a SemVer-shaped token. **Do not assert a specific version number** —
the checkout deliberately has a stale baked PHAR version (2.2.2) versus tag
2.2.3; asserting either would make the test break on the next release.

Also accept the literal token `unreleased`: `actions/checkout` defaults to a
shallow clone, so `git describe --tags --abbrev=0` fails in CI and Laravel Zero
falls back to `unreleased` (confirmed by a red CI run during implementation).
The pattern is therefore `(?:\d+\.\d+\.\d+|unreleased)`.

**Verify**: `vendor/bin/pest --colors=never --filter="version command"` → pass.

## Test plan

- New tests: `tests/Feature/InstallationStateTest.php` (HOME isolation,
  metadata presence, update path restoration, network-failure propagation) and
  `tests/Feature/AssetTest.php` (temp-dir branch).
- Structural pattern: `tests/Feature/CommandSuiteTest.php` — plain Pest `test()`
  functions, no dataset boilerplate.
- Verification: `vendor/bin/pest --colors=never` → all pass, at least 5 new
  tests added beyond the existing 2.

## Done criteria

- [ ] `composer update --lock --no-install --no-interaction` run; `composer
      validate --strict --no-check-publish --no-check-version` reports "valid" with no lock error
- [ ] `.github/workflows/tests.yml` exists and runs Pest + Composer validation
      on push/PR to `main`
- [ ] `vendor/bin/pest --colors=never` exits 0 with the original 2 tests plus
      the new characterization tests
- [ ] No test performs a real HTTP request
- [ ] `app/` and `composer.json` are unmodified (`git status`)
- [ ] No file includes a credential value; no `.env` content was read or copied
- [ ] `plans/README.md` status row for 001 updated to SELESAI (or `TERHALANG: …`)

## STOP conditions

Stop and report back (do not improvise) if:

- The excerpts above do not match live code (drift since `056da37`).
- `composer update --lock` changes more than the lock hash/metadata, or requires
  network access that is unavailable.
- A test reveals a genuine bug in `app/` — report it; fixing it is plan 002+
  scope only if the operator agrees.
- Creating `/home/sail` or isolating HOME breaks the Laravel Zero test boot in a
  way that cannot be contained with `beforeEach`/`afterEach`.
- Any verification command fails twice after a reasonable fix attempt.

## Maintenance notes

- These tests lock in *current* behavior, including the non-obvious rule that
  the metadata file — not the directory — defines "installed". If plan 002 or
  003 intentionally changes that rule, update the corresponding test and call
  it out explicitly in that plan's PR.
- The HOME-isolation helper is the foundation for later Sail tests. Keep it in
  `tests/Pest.php` or a shared trait rather than duplicating it per file.
- Deferred deliberately: fixing the `sudo_shell_exec` path handling in
  `app/helpers.php:231-240` and `BaseInstaller.php:444-451`. It is an installer-
  global security change, not a Sail-specific one, and the operator deferred it.
- Deferred deliberately: rebuilding the stale PHAR (reports 2.2.2 while tag is
  2.2.3). Plan 003 pins the CLI via Composer instead, which makes the baked
  version irrelevant to Sail boot. Releasing a corrected PHAR is a separate
  release-engineering task.
