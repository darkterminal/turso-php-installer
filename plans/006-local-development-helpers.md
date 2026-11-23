# Plan 006 — Re-test and fully document the local development helpers

**Priority:** P1 · **Depends on:** 001 (Pest gate), #30 (PHP 8.1 dropped), #32 (unstable channel dropped)
**Scope:** `token:*`, `server:*`, `sqld:*` commands, their services, and their public documentation.

---

## Objective

The installer's local development helpers have never been exercised by the test
suite beyond command registration, and their documentation is a single thin page
(`docs/user-manual/tokens.md`) that lists commands without options, defaults,
prerequisites, or failure modes.

This plan re-tests every helper against the current code, records what actually
happens, and turns the results into complete reference documentation. It is a
**test-and-document** plan: behaviour changes are out of scope. Any bug found
becomes a separate issue, not a smuggled fix.

## Why now

- #30 and #32 changed install/update behaviour. The helpers share the install
  directory, metadata and token store, so they need a fresh baseline.
- `docs/user-manual/tokens.md` had three wrong command names and missing
  arguments until #24. Nothing guarantees the rest of the page is accurate.
- The README now points readers at GitHub Pages for the full command surface;
  that surface must actually be complete.

## Current state (verified on `main` after #32)

| Area | Evidence | Gap |
|---|---|---|
| Test coverage | `tests/Feature/CommandSuiteTest.php` only asserts the commands are registered | No behaviour tests for any helper |
| Token store | `app/Services/DatabaseToken/DatabaseTokenGenerator.php` uses `\LibSQL` and `openssl genpkey` | Requires the installed extension; untested |
| Token guards | All four `app/Commands/Token/*.php` call `checkIfAlreadyInstalled()` | Guard behaviour untested |
| Certificates | `app/Services/LibsqlServer/LibsqlServerGenerator.php` shells out to `python3` + `app/scripts/*.py` | Requires `cryptography`; untested |
| sqld environments | `app/Services/Sqld/Environment.php`, JSON store under the install dir | Validation rules untested |
| sqld runtime | `RunSqldServer` shells `sqld`; `OpenDatabaseServer` shells `turso db shell` | External binaries; daemon lifecycle untested |
| Platform limits | `sqld:server-run`, `sqld:open-db`, `sqld:daemon-*` exit on Windows | Not documented |
| Docs | `docs/user-manual/tokens.md` covers all three families in ~60 lines | No options, defaults, prerequisites, or error catalogue |

### Local environment facts (checked, not assumed)

- `ext-libsql` is **not loaded** in the working PHP runtime.
- `openssl` 3.4.1 is available.
- `python3` is available but `cryptography` is **not installed**.
- `sqld` and `turso` binaries exist at `~/.turso/`.
- `.github/workflows/tests.yml` does **not** install the extension, so any test
  that touches `\LibSQL` would fail in CI as things stand.

These facts shape the steps below. Re-check them before executing; do not trust
this table blindly.

---

## Prerequisites

1. Linux or macOS. Windows is explicitly unsupported by the sqld runtime
   commands; do not attempt to "fix" that here.
2. A stable extension install for the running PHP version, or an isolated
   install created by the installer itself during the test run.
3. `openssl` on PATH.
4. `python3` with `cryptography` importable. Prefer a dedicated virtualenv;
   never install into the system interpreter silently.
5. `sqld` and `turso` binaries on PATH for the runtime steps. If absent, record
   the step as SKIPPED with the reason — do not fake a pass.

---

## Steps

### Step 1 — Freeze current behaviour with characterization tests

Create `tests/Feature/LocalDevelopmentHelpersTest.php` (or one file per family)
using the isolated-HOME pattern from `InstallationStateTest.php`.

Cover at minimum:

- Every helper command's definition: arguments, options, defaults
  (`token:create --expire=7`, `token:show --fat/--roa/--pkp/--pkb`,
  `server:ca-cert-create --expiry=30`, `sqld:env-new --variables/--force`,
  `sqld:server-run --d|daemon`).
- The `checkIfAlreadyInstalled()` guard on all four token commands: with no
  metadata, each must fail cleanly and non-zero.
- `sqld:env-new` validation: duplicate name without `--force`, malformed
  `--variables`, relative `SQLD_DB_PATH` resolution.
- `sqld:server-run` refusal when both `SQLD_GRPC_LISTEN_ADDR` and
  `SQLD_PRIMARY_GRPC_URL` are set.
- `sqld:daemon-list` / `sqld:daemon-kill` with an empty or stale daemon file.

Tests that need `\LibSQL` must be guarded so the suite stays honest:

```php
if (!extension_loaded('libsql')) {
    $this->markTestSkipped('ext-libsql is not loaded; run the helpers job.');
}
```

**Verify:** `vendor/bin/pest --colors=never` is green; skipped tests are
reported as skipped, never as passed.

### Step 2 — Add a CI job that can actually run the helper tests

Extend `.github/workflows/tests.yml` with a second job (`helpers`) that:

1. Sets up PHP 8.3 (match the existing job).
2. Installs the stable extension with the installer itself:
   `php turso-php-installer install -n --php-version=8.3`.
3. Asserts `php -m | grep -qi '^libsql$'`.
4. Runs `vendor/bin/pest --colors=never --group=helpers` (or the whole suite).

Keep the existing `test` job unchanged so the fast gate stays fast.

**Verify:** the new job is green on the PR; the old job still passes.

### Step 3 — Manual re-test matrix: tokens

With an isolated HOME and a real stable install:

```bash
turso-php-installer token:create demo --expire=7
turso-php-installer token:list
turso-php-installer token:show demo
turso-php-installer token:show demo --fat
turso-php-installer token:show demo --roa
turso-php-installer token:show demo --pkp
turso-php-installer token:show demo --pkb
turso-php-installer token:delete demo
```

Record for each: exit code, output shape, files created under the install dir,
and what happens on a missing/expired token. Verify the JWT actually validates
against the generated public key.

**Verify:** every command behaves as documented in Step 6; discrepancies become
issues.

### Step 4 — Manual re-test matrix: certificates

```bash
turso-php-installer server:check
turso-php-installer server:cert-store-get
turso-php-installer server:cert-store-set /tmp/opencode/cert-store
turso-php-installer server:ca-cert-create --expiry=30
turso-php-installer server:ca-cert-list
turso-php-installer server:ca-cert-show
turso-php-installer server:ca-peer-cert-create
turso-php-installer server:ca-peer-cert-list
turso-php-installer server:ca-peer-cert-show
turso-php-installer server:ca-peer-cert-delete
turso-php-installer server:ca-cert-delete
```

Run `server:check` twice: once without `cryptography` (must fail with remediation
hints, never install silently) and once inside a venv that has it.

**Verify:** certificate files land in the configured store, `openssl x509`
parses them, and expiry matches `--expiry`.

### Step 5 — Manual re-test matrix: sqld

```bash
turso-php-installer sqld:env-new demo --variables='{"SQLD_HTTP_LISTEN_ADDR":"127.0.0.1:8080"}'
turso-php-installer sqld:env-list
turso-php-installer sqld:env-show demo
turso-php-installer sqld:env-edit demo
turso-php-installer sqld:server-run demo demo --daemon
turso-php-installer sqld:daemon-list
curl -s http://127.0.0.1:8080/health
turso-php-installer sqld:open-db demo demo
turso-php-installer sqld:daemon-kill <pid>
turso-php-installer sqld:env-delete demo
```

Record: daemon liveness verification, log file locations, the gRPC conflict
guard, and behaviour when the referenced token is deleted.

**Verify:** the daemon starts, serves, and is killed cleanly; no orphan
processes or files outside the isolated HOME.

### Step 6 — Rewrite the documentation from the recorded results

Split the single page into three complete references:

- `docs/user-manual/tokens.md` — rewritten: prerequisites, every option and
  default, examples, token lifetime, storage location, failure modes.
- `docs/user-manual/server-certificates.md` — new: `server:check` requirements,
  cert store configuration, CA and peer lifecycle, expiry, Python dependency.
- `docs/user-manual/sqld.md` — new: environments, variables and their defaults,
  the gRPC conflict rule, daemon lifecycle, `open-db` Turso CLI dependency,
  platform limits.

Each page must state: prerequisites, exact signatures, options with defaults,
at least one working example, where files are written, and what failure looks
like. No emoji. No claims that were not observed in Steps 3–5.

Wire them up:

- `docs/_data/nav.yml` — add the two new pages.
- `docs/user-manual/index.md` — command surface and next steps.
- `README.md` — documentation list.
- `docs/index.html` — only if a claim there becomes stale.

**Verify:** `python3 scripts/check_docs.py` prints three `OK:` lines; every new
page is reachable from nav and index.

### Step 7 — Final gate and handoff

Run the full verification set below, inspect `git diff main...HEAD`, confirm no
behaviour change slipped in, and report exactly what was tested, skipped, and
why.

---

## Verification commands (all must pass before merge)

```bash
vendor/bin/pest --colors=never
python3 scripts/check_docs.py
bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint
composer validate --strict --no-check-publish --no-check-version
git diff --check
```

Plus the Step 3–5 matrices, with results recorded in the PR body.

## Done criteria

- Every helper command has at least one characterization test; token-store tests
  run for real in the new CI job.
- The three documentation pages exist, are linked from nav/index/README, and
  contain options, defaults, prerequisites, examples and failure modes.
- No documented claim contradicts an observed result.
- All verification commands green; CI `test`, `helpers` and `docs-checks` green.
- No release, tag or version bump.

## STOP conditions

Stop and report instead of improvising if:

- The stable extension cannot be installed in CI or locally.
- A helper writes outside the isolated HOME or touches the real user config.
- `cryptography` would require a system-level install.
- A test reveals a behaviour bug — file a separate issue; do not fix it inside
  this plan's PR.
- `sqld` or `turso` binaries are unavailable and cannot be obtained without
  network assumptions the owner has not approved.

## Out of scope

- Changing helper behaviour, signatures or storage layout.
- Windows and FrankenPHP support.
- Adding new helper commands.
- Release management of any kind.

## Maintenance notes

- The helper suite depends on external binaries (`openssl`, `python3`,
  `cryptography`, `sqld`, `turso`). Re-check their availability before each run;
  record SKIPPED honestly rather than deleting the step.
- If a future plan changes install paths or metadata, re-run Steps 3–5: the
  helpers share that state.
