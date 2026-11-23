# Plan 002: Replace the broken version gate with a fail-hard extension preflight

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving to the next step. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 056da37..HEAD -- docs/user-manual/laravel-sail.md plans/001-verification-baseline.md`
> If the Sail doc changed since this plan was written, compare the "Current
> state" excerpt against the live file before proceeding; on a mismatch, treat
> it as a STOP condition. Plan 001 must be DONE first — its tests are the gate
> for the installer behavior relied on here.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: LOW
- **Depends on**: plans/001-verification-baseline.md
- **Category**: bug
- **Planned at**: commit `056da37`, 2026-11-23

## Why this matters

The current `run_turso` script compares the **installer CLI version** (parsed
from the binary's summary line) against GitHub tags of the installer repo, then
runs `turso-php-installer update` when they differ. `update` only re-downloads
the **libSQL extension**; it never changes the CLI version. So the condition is
permanently true whenever the baked PHAR version lags a tag — which it does on
this checkout (PHAR reports 2.2.2, tag is 2.2.3) — and every container boot
performs an unnecessary download. Worse, the update's exit code is ignored and
the script ends with `exec "$@"` while the Dockerfile passes no arguments, so
`exec "$@"` is a no-op that returns 0. A failed update therefore still starts
the app with a missing extension, and the "gate" reports success.

The operator decided the CLI will be **pinned via Composer at image build time**
(plan 003) and the extension will be **owned by the image** (plan 003). That
removes the reason for any download at boot. `run_turso` becomes a read-only
preflight: verify the extension is visible to the PHP SAPI, fail hard if not.

## Current state

`docs/user-manual/laravel-sail.md`, lines 44–93 — the complete `run_turso` stub:

```bash
#!/usr/bin/env bash

# Filename: run_turso

VERSION_FILE="/home/sail/.cache/turso_version"
CACHE_DURATION=604800 # 7 days in seconds

get_latest_version() {
    curl -s https://api.github.com/repos/darkterminal/turso-php-installer/tags | \
    grep '"name":' | \
    head -n 1 | \
    sed -n 's/.*"name": "\([0-9.]*\)".*/\1/p'
}

if [ -d "/opt/extensions" ] && [ "$(ls -A /opt/extensions)" ]; then
    CURRENT_VERSION=$(/home/sail/.config/composer/vendor/bin/turso-php-installer | head -n 2 | sed -n 's/.*turso-php-installer[[:space:]]*\([0-9.]*\).*/\1/p')

    if [ -f "$VERSION_FILE" ]; then
        FILE_AGE=$(($(date +%s) - $(stat -c %Y "$VERSION_FILE")))
        if [ "$FILE_AGE" -lt "$CACHE_DURATION" ]; then
            LATEST_VERSION=$(cat "$VERSION_FILE")
        else
            LATEST_VERSION=$(get_latest_version)
            echo "$LATEST_VERSION" > "$VERSION_FILE"
        fi
    else
        mkdir -p "$(dirname "$VERSION_FILE")"
        LATEST_VERSION=$(get_latest_version)
        echo "$LATEST_VERSION" > "$VERSION_FILE"
    fi

    echo "Turso PHP Installer Current Version: $CURRENT_VERSION"
    echo "Turso PHP Installer Latest Version: $LATEST_VERSION"

    if [ "$CURRENT_VERSION" != "$LATEST_VERSION" ]; then
        echo "Updating turso-php-installer to the latest version..."
        /home/sail/.config/composer/vendor/bin/turso-php-installer update
    else
        echo "Turso PHP Installer is up to date."
    fi

    exec "$@"
else
    echo "/opt/extensions is empty or does not exist. Running update..."
    /home/sail/.config/composer/vendor/bin/turso-php-installer update
    exec "$@"
fi
```

Facts from the implementation (needed to justify the rewrite):
- `app/Commands/Installer/UpdateTursoExtension.php:29-39` — `update()` only
  checks `checkIfAlreadyInstalled()` and calls `$installer->update()`; it returns
  `1` when nothing is installed.
- `app/Services/Installation/BaseInstaller.php:453-477` — `update()` restores
  php.ini/extension dir from metadata, re-downloads the extension, rewrites
  php.ini and verifies. Nothing in it updates the CLI package.
- `app/Commands/Installer/VersionTursoExtension.php:28` + `config/app.php:81` —
  the version printed comes from `config('app.version')` = `app('git.version')`,
  i.e. the CLI build itself. There is no `self-update` command anywhere in
  `app/Commands/Installer/`.
- `docs/user-manual/laravel-sail.md:178` — `CMD ["/bin/bash", "-c",
  "/usr/local/bin/run_turso && start-container"]` passes zero arguments to
  `run_turso`, so its `exec "$@"` is a no-op.
- `app/helpers.php:311-318` — `check_libsql_installed()` is
  `php -m | grep libsql`; this is the existing "is it loaded" primitive and is
  the contract the new preflight should follow (small, dependency-free).

Constraints from the operator's approved direction:
- CLI version is pinned by Composer in the Dockerfile (plan 003) — **no**
  `self-update` command, **no** GitHub tag polling.
- Extension is image-owned; there is no `/opt/extensions` bind mount after
  plan 003 — **no** download at boot.
- Preserve the existing `start-container` contract (plan 003 restores
  `ENTRYPOINT`). This plan only changes `run_turso`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Shell syntax | `bash -n <path-to-extracted-run_turso>` | exit 0, no output |
| Lint helper (optional) | `grep -nE 'api\.github\.com|exec "\$@"|update$|CACHE_DURATION' <path>` | no matches |
| Tests (gate from 001) | `vendor/bin/pest --colors=never` | all pass |
| Git state | `git status --short -b` | only in-scope files changed |

There is no `shellcheck` on this machine — do not add it. `bash -n` is the
accepted syntax gate.

## Scope

**In scope**:
- `docs/user-manual/laravel-sail.md` — replace only the `run_turso` code block
  (lines 45–93) and add a short paragraph explaining that the CLI is pinned at
  build time and the boot step only verifies the extension.

**Out of scope** (do NOT touch, even though they look related):
- The Dockerfile block (lines 96–179) and compose block (lines 182–210) — plan 003.
- `README.md`, `docs/README.md`, docs nav, PHP-version claims — plan 005.
- Any file under `app/` — the installer behavior is correct; the *caller* is wrong.
- Rebuilding `builds/turso-php-installer` or changing `composer.json` version.

## Git workflow

- Follow `plans/WORKFLOW.md` (standing workflow). Create branch
  `plan/002-sail-preflight`; never work directly on `main`.
- Issue `Plan 002 — ...` must exist BEFORE the branch is created. Put
  `Refs #<issue>` in every commit body and `Closes #<issue>` in the PR body,
  together with this plan path.
- Commit message style: `fix(docs): make Sail boot fail hard on missing libsql extension`.
- Open a PR against `main`, run every verification gate, double-check done
  criteria and CI, then squash-merge only when clean. Update the status row in
  `plans/README.md` in a small follow-up commit on `main`.

## Steps

### Step 1: Extract the stub to a temporary file and prove the current failure mode

Write the block from "Current state" to a temp path, e.g.
`/tmp/opencode/sail-audit-run_turso` (outside the repo), and run
`bash -n /tmp/opencode/sail-audit-run_turso` → exit 0. This confirms the baseline
is syntactically valid and that the file you are editing is the right one.

Optional demonstration (no repo changes): running
`bash -c 'set -- ; exec "$@"; echo reached'` prints `reached`, proving the
no-argument `exec "$@"` returns control rather than terminating. Record the
observation if you want it in the PR description.

**Verify**: `bash -n` exit 0 and the temp file's first line is
`#!/usr/bin/env bash`.

### Step 2: Replace the version gate with a read-only preflight

Replace lines 45–93 in `docs/user-manual/laravel-sail.md` with a script with
these properties, in this order:

1. `set -euo pipefail` at the top.
2. A single resolved binary path variable:
   `TURSO_INSTALLER_BIN="/home/sail/.config/composer/vendor/bin/turso-php-installer"`.
   It is used only for an optional diagnostic, never to decide whether to run
   an update. If plan 003 changes the install location, this line must change
   with it — add a comment saying so.
3. Verify the extension is loaded by the PHP CLI SAPI, using the repository's
   existing primitive: `php -m | grep -qi '^libsql$'`. On failure print an
   actionable message (which php.ini to check) and `exit 1`.
4. Optionally print the pinned CLI version via `"$TURSO_INSTALLER_BIN" version`
   for diagnostics, guarded so a missing binary does not abort the container;
   never parse it and never act on it.
5. End with `exit 0`. There is no `exec "$@"` because this script is a preflight
   called by an entrypoint, not a command wrapper.

Do not include: `curl`, the tags API, `VERSION_FILE`, `CACHE_DURATION`, any
`update` invocation, or any download.

**Verify**: extract the new block to a temp file and run
`bash -n <file>` → exit 0; then
`grep -nE 'api\.github\.com|exec "\$@"|turso-php-installer update|CACHE_DURATION' <file>`
→ no matches.

### Step 3: Add explanatory prose and a verification step to the guide

Immediately after the new code block, add a short paragraph (2–4 sentences)
stating: the installer CLI is pinned to a version in the Dockerfile at build
time; the extension is installed into the image and is not mounted from the
host; this boot script only verifies the extension and fails the container when
it is missing. Do not promise an auto-update.

Before the `## Build` section (currently line 212), add a post-boot verification
snippet so users can confirm the loaded extension, e.g.
`sail exec laravel.test php -m | grep libsql` (adapt to the service name used in
plan 003). Note that other SAPIs need their own conf.d drop-in.

**Verify**: `grep -n 'sail exec' docs/user-manual/laravel-sail.md` → at least one
match; `grep -n 'auto-update' docs/user-manual/laravel-sail.md` → no matches
(positive claims only).

### Step 4: Re-run the gates

**Verify**: `vendor/bin/pest --colors=never` → all pass (the gate from plan 001
must be green). `git diff --check` → exit 0. `git status --short` shows only
`docs/user-manual/laravel-sail.md` plus, if you chose to update it, the status
row in `plans/README.md`.

## Test plan

- No new PHP tests in this plan: the script is a docs stub, not executable PHP.
- The new characterization tests from plan 001 are the gate — run the full
  suite once before and once after the edit.
- Shell-level verification is `bash -n` plus the two `grep` assertions in steps
  2 and 3. These become permanent CI checks in plan 004.
- Failure-path reasoning to include in the PR description: if `php -m` does not
  list libsql, the script exits 1, so the CMD/entrypoint chain in plan 003 will
  prevent the container from starting with a broken extension.

## Done criteria

- [ ] `run_turso` block no longer references the GitHub tags API, a version
      cache file, or `turso-php-installer update` (`grep` returns no matches)
- [ ] `run_turso` starts with `set -euo pipefail`, checks `php -m` for libsql,
      and exits 1 on failure
- [ ] No `exec "$@"` remains; script ends with `exit 0`
- [ ] `bash -n` passes on the extracted stub
- [ ] Guide explains Composer pinning and image-owned extension; no
      auto-update claim remains
- [ ] A post-`sail up` verification command is present
- [ ] `vendor/bin/pest --colors=never` exits 0
- [ ] Only `docs/user-manual/laravel-sail.md` and the tracker status row changed
- [ ] `plans/README.md` status row for 002 updated to SELESAI (or `TERHALANG: …`)

## STOP conditions

Stop and report back (do not improvise) if:

- Plan 001 is not DONE, or its Pest suite is red.
- The `run_turso` block differs materially from the excerpt above.
- You find that `php -m` inside Sail is not the correct SAPI to check (e.g. the
  published runtime uses php-fpm only) — report it; plan 003 owns the runtime
  decision.
- The guide's Dockerfile block must change for the new `TURSO_INSTALLER_BIN`
  path to be correct — that is plan 003, do not partially edit it here.
- Any verification command fails twice after a reasonable fix attempt.

## Maintenance notes

- This script and the Dockerfile in plan 003 are a pair: changing the Composer
  install directory, the PHP version, or the extension location requires
  updating both. Plan 004 extracts both into real stub files with one version
  variable to make that coupling visible.
- The `libsql` name check follows `check_libsql_installed()` in
  `app/helpers.php:311-318`. If the extension's reported module name changes in
  a future release, this check and that helper must change together.
- Deferred deliberately: any `self-update` capability for the CLI, and any
  runtime download of the extension. Both were rejected by the operator; the
  CLI is pinned by Composer and the extension is owned by the image.
