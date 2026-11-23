# Plan 003: Align the Sail runtime with upstream Sail 8.5 and make the extension image-owned

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving to the next step. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 056da37..HEAD -- docs/user-manual/laravel-sail.md plans/002-sail-preflight.md`
> Plan 001 and plan 002 must be DONE first. This plan changes the Dockerfile and
> compose stubs that plan 002's preflight depends on.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: plans/001-verification-baseline.md, plans/002-sail-preflight.md
- **Category**: migration
- **Planned at**: commit `056da37`, 2026-11-23

## Why this matters

The Dockerfile stub in the guide is a full copy of a Sail runtime from early
2025. Current upstream Sail 1.x uses PHP 8.5 as default, Node 24, PostgreSQL 18,
the sury.org PHP repository, `PHP_EXTENSIONS`, `LANG=C.UTF-8`, Playwright deps,
`cpx`, a Git safe-directory entry, and `ENTRYPOINT ["start-container"]`. The stub
has none of that; it still uses Node 22, PG 17, the Ondrej PPA, and a `CMD`
chain that bypasses Sail's user/argument contract. Its `COPY php.ini` destination
is hardcoded to `/etc/php/8.3/...` while every other PHP path is a `<version>`
placeholder, so a reader who chooses 8.5 gets the Sail ini in the wrong directory.

This plan refreshes the runtime to the current upstream baseline and applies a
small, explicit Turso delta: pin the CLI via Composer at build time, install the
extension into an image-owned directory, drop the host bind mount, and put the
preflight ahead of `start-container` while preserving its argument forwarding.

## Current state

`docs/user-manual/laravel-sail.md:96-179` — the current Dockerfile stub. Key
lines:

```dockerfile
FROM ubuntu:24.04
ARG WWWGROUP
ARG NODE_VERSION=22
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=17
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
# ... Ondrej PPA via keyserver.ubuntu.com, manual NodeSource + Yarn apt repos ...
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php<version>
RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN mkdir -p /root/.config/composer \
    && composer global require darkterminal/turso-php-installer --working-dir=/root/.config/composer \
    && chmod +x /root/.config/composer/vendor/bin/turso-php-installer \
    && mkdir -p /opt/extensions \
    && chmod -R 775 /opt/extensions \
    && /root/.config/composer/vendor/bin/turso-php-installer install -n --php-version=<version> --extension-dir=/opt/extensions --php-ini=/etc/php/<version>/cli/php.ini \
    && chown -R sail:sail /opt/extensions \
    && mkdir -p /home/sail/.config/composer \
    && chown -R sail:sail /home/sail/.config \
    && su sail -c "composer global require darkterminal/turso-php-installer --working-dir=/home/sail/.config/composer" \
    && echo 'export PATH="/home/sail/.config/composer/vendor/bin:$PATH"' >> /home/sail/.bashrc
COPY run_turso /usr/local/bin/run_turso
RUN chmod +x /usr/local/bin/run_turso
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container
EXPOSE 80/tcp
CMD ["/bin/bash", "-c", "/usr/local/bin/run_turso && start-container"]
```

`docs/user-manual/laravel-sail.md:182-210` — compose stub; the relevant volume
is `- "./extensions:/opt/extensions"` at line 204, and `build.context` is
`"./docker/<version>"` at line 186.

Upstream facts verified by direct fetch on 2026-11-23 (re-fetch before editing —
see step 1):
- `https://raw.githubusercontent.com/laravel/sail/1.x/runtimes/8.5/Dockerfile`:
  `ARG NODE_VERSION=24`, `ARG POSTGRES_VERSION=18`, `ARG PHP_EXTENSIONS=""`,
  `ENV LANG=C.UTF-8`, `ENV PLAYWRIGHT_BROWSERS_PATH=0`, sury keyring +
  `https://packages.sury.org/php/ noble main`, NodeSource via
  `curl -fsSL https://deb.nodesource.com/setup_$NODE_VERSION.x | bash -`,
  `corepack` + `corepack prepare yarn@stable --activate`, PGDG repo,
  `MYSQL_CLIENT` / `postgresql-client-$POSTGRES_VERSION` installs, `cpx` into
  `/usr/local/bin`, `npx -y playwright install-deps`,
  `git config --global --add safe.directory /var/www/html`,
  `COPY php.ini /etc/php/8.5/cli/conf.d/99-sail.ini`, and
  `ENTRYPOINT ["start-container"]` with no `CMD`.
- `https://raw.githubusercontent.com/laravel/sail/1.x/runtimes/8.5/start-container`:
  validates `SUPERVISOR_PHP_USER` is `sail` or `root`, remaps `WWWUSER` via
  `usermod`, `chmod -R ugo+rw /.composer`, then `exec "$@"` (via `gosu` when
  non-root) or `exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf`.
- `https://raw.githubusercontent.com/laravel/sail/1.x/src/Console/PublishCommand.php`
  and `.../src/SailServiceProvider.php`: `sail:publish` publishes only the
  `sail-docker` and `sail-database` tags to `docker/` and rewrites an existing
  compose file; it does not create the compose file and does not publish the
  `sail` binary (that is a separate `sail-bin` tag).
- `https://laravel.com/framework/docs/13.x/sail`: default PHP is 8.5; published
  runtimes are 8.0–8.5; compose file is `compose.yaml`.

Implementation facts that constrain the delta:
- `app/Services/Installation/BaseInstaller.php:453-477` — `update()` reads
  metadata from HOME and re-downloads the extension. After this plan the boot
  path no longer calls `update()`, so the old root-vs-sail HOME split at
  `docs/user-manual/laravel-sail.md:156-166` is no longer load-bearing. Keep
  build-time and runtime state consistent anyway.
- `app/ValueObjects/Asset.php:36-44` — `/home/sail` triggers a HOME-based temp
  dir. Still relevant at build time only.
- `app/helpers.php:311-318` — `check_libsql_installed()` uses `php -m | grep libsql`;
  the preflight from plan 002 uses the same contract.
- `composer.json:4` is `2.2.3`; `git tag` lists `2.2.3`; the committed PHAR reports
  `2.2.2`. Pinning by Composer makes that baked number irrelevant to Sail boot.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Re-fetch upstream | `curl -fsSL --max-time 30 https://raw.githubusercontent.com/laravel/sail/1.x/runtimes/8.5/Dockerfile -o /tmp/opencode/sail-upstream-8.5.Dockerfile` | exit 0, file non-empty |
| Confirm pin available | `composer show darkterminal/turso-php-installer --all` | version `2.2.3` listed as stable |
| Compose shape | `docker compose -f <extracted-compose> config -q` | exit 0 (no daemon needed) |
| Shell syntax | `bash -n <extracted-entrypoint>` | exit 0 |
| Placeholder gate | `grep -n '<version>' docs/user-manual/laravel-sail.md` | no matches |
| Tests (gate) | `vendor/bin/pest --colors=never` | all pass |
| Git state | `git status --short -b` | only in-scope files changed |

`docker` is available locally (29.10.0). A full `docker build` needs network and
an available daemon; it was **not** run during planning. See step 6.

## Scope

**In scope**:
- `docs/user-manual/laravel-sail.md` — Dockerfile block (lines 96–179), compose
  block (lines 182–210), the `<version>` replacement instruction at lines 95 and
  181, and the folder tree at lines 15–40 (compose filename / extension folder).

**Out of scope** (do NOT touch, even though they look related):
- `run_turso` content — plan 002 owns it. You may copy it verbatim into the new
  entrypoint wrapper but must not rewrite its logic.
- `README.md`, `docs/README.md`, `docs/index.html`, `docs/_data/nav.yml` — plan 005.
- `app/`, `composer.json`, `build.sh`, `builds/`, `composer.lock`.
- Publishing a new release or rebuilding the PHAR.

## Git workflow

- Follow `plans/WORKFLOW.md` (standing workflow). Create branch
  `plan/003-upstream-runtime-alignment`; never work directly on `main`.
- Issue `Plan 003 — ...` must exist BEFORE the branch is created. Put
  `Refs #<issue>` in every commit body and `Closes #<issue>` in the PR body,
  together with this plan path.
- Commit message style: `fix(docs): refresh Sail runtime and pin installer via Composer`.
- Open a PR against `main`, run every verification gate, double-check done
  criteria and CI, then squash-merge only when clean. Update the status row in
  `plans/README.md` in a small follow-up commit on `main`.

## Steps

### Step 1: Re-fetch upstream and gate on drift

Run the `curl` command from the table, plus the same for
`https://raw.githubusercontent.com/laravel/sail/1.x/runtimes/8.5/start-container`
and `https://raw.githubusercontent.com/laravel/sail/1.x/src/Console/PublishCommand.php`.

Compare the fetched Dockerfile against the facts in "Current state". If
`NODE_VERSION`, `POSTGRES_VERSION`, `PHP_EXTENSIONS`, the base image, the PHP
repo, or the `ENTRYPOINT` line no longer match, STOP and report the diff. Do not
paste a half-updated stub.

**Verify**: all three files non-empty (`wc -c > 0`) and the fetched Dockerfile
contains `ENTRYPOINT ["start-container"]`.

### Step 2: Confirm the Composer pin target

Run `composer show darkterminal/turso-php-installer --all`. Default pin is
`2.2.3` (the current tag and the `composer.json` version). If `2.2.3` is not
available on the configured Packagist repository, STOP and report — do not
silently fall back to `2.2.2`, because that is the stale baked version.

**Verify**: the command output lists `2.2.3` among stable versions.

### Step 3: Replace the Dockerfile stub with upstream 8.5 plus the Turso delta

Use the fetched upstream 8.5 Dockerfile as the base. Keep all upstream lines
(base image, ARGs, ENV, apt/bootstrap blocks, Node/Yarn setup, cpx, Playwright
deps, `safe.directory`, user creation, `EXPOSE 80/tcp`, `ENTRYPOINT`). Then apply
exactly these deltas:

1. Add `ARG PHP_VERSION=8.5` near the other ARGs. Replace every `<version>`
   token in the stub with `${PHP_VERSION}` — apt package names, `setcap`, the
   installer `--php-version` / `--php-ini` flags, and the `COPY php.ini`
   destination. This is a deliberate deviation from upstream's per-directory
   hardcoded path; it gives the guide one source of truth and fixes the
   `/etc/php/8.3/...` bug. Docker expands `ARG` in `RUN` and `COPY` destinations.
2. Install the CLI **once**, into `/home/sail/.config/composer`, with an explicit
   version:
   `RUN mkdir -p /home/sail/.config/composer /opt/extensions \
       && HOME=/home/sail composer global require darkterminal/turso-php-installer:2.2.3 --working-dir=/home/sail/.config/composer \
       && chmod +x /home/sail/.config/composer/vendor/bin/turso-php-installer \
       && HOME=/home/sail /home/sail/.config/composer/vendor/bin/turso-php-installer install -n --php-version=${PHP_VERSION} --extension-dir=/opt/extensions --php-ini=/etc/php/${PHP_VERSION}/cli/php.ini \
       && chown -R sail:sail /home/sail/.config /opt/extensions`
   Place this **after** upstream's `sail` user creation. Do not keep the
   `/root/.config/composer` copy, do not use `su sail -c`, and do not use an
   unpinned `composer global require`.
   If a published-tagged install is unavailable (e.g. the tag exists on GitHub
   but not Packagist), STOP and report rather than installing from a branch.
3. Keep the four `COPY` lines (`run_turso`, `start-container`,
   `supervisord.conf`, `php.ini`) and their chmods. The `php.ini` destination
   must use `${PHP_VERSION}`.
4. Add a fifth small file, `sail-entrypoint`, with exactly:
   ```bash
   #!/usr/bin/env bash
   set -euo pipefail
   /usr/local/bin/run_turso
   exec /usr/local/bin/start-container "$@"
   ```
   `COPY sail-entrypoint /usr/local/bin/sail-entrypoint` and chmod it.
5. Replace the `CMD` chain with
   `ENTRYPOINT ["/usr/local/bin/sail-entrypoint"]` and no `CMD`. This preserves
   upstream `start-container` semantics (argument forwarding, `gosu`, supervisord
   fallback) while running the preflight first.
6. Add a comment in the stub noting that `php.ini` comes from `sail:publish` and
   that the extension lives in `/opt/extensions` inside the image.

**Verify**: `grep -n '<version>' docs/user-manual/laravel-sail.md` → no matches;
`grep -nE 'ENTRYPOINT|ARG PHP_VERSION|turso-php-installer:2\.2\.3|/root/\.config/composer|./extensions:/opt/extensions' docs/user-manual/laravel-sail.md`
→ shows the new ENTRYPOINT/ARG/pin lines and **no** root-composer line. Extract
the Dockerfile block to a temp file and confirm it ends with the ENTRYPOINT line.

### Step 4: Update the compose stub

In lines 182–210:
- Rename every reference to `docker-compose.yml` in the surrounding prose and
  folder tree to `compose.yaml` (the file `sail:publish` actually rewrites). Do
  not create a new compose file in the guide — tell the reader to edit the one
  their Laravel app already has.
- Add `PHP_VERSION: "${PHP_VERSION:-8.5}"` under `build.args` so the Dockerfile
  ARG is driven from one place.
- Delete the `- "./extensions:/opt/extensions"` volume. Keep
  `- ".:/var/www/html"`.
- Keep the rest of upstream's service shape (extra_hosts, ports, environment,
  networks) as fetched.
- Update the `image:` name so it cannot collide with an unrelated image; keep
  it deterministic, e.g. `sail-${PHP_VERSION:-8.5}/app` is acceptable if it
  matches the context path.

**Verify**: extract the compose block to
`/tmp/opencode/sail-audit-compose.yaml`, substituting the concrete values
`8.5`, `${WWWGROUP}`→`1000`, `${WWWUSER}`→`1000`, `${PWD}`→`/tmp`, then run
`docker compose -f /tmp/opencode/sail-audit-compose.yaml config -q` → exit 0.
Then `grep -n 'extensions' /tmp/opencode/sail-audit-compose.yaml` → no matches.

### Step 5: Update the folder tree and prose

In lines 15–40 remove the `extensions # <- Create this folder …` entry and
change `docker-compose.yml # <- Sail Docker Compose` to
`compose.yaml # <- existing Sail Compose file, edited in place`. Adjust the
`<version>` instruction at lines 95/181 to say the stub uses `PHP_VERSION` in one
place (the compose `build.args`) and that all snippets follow it.

**Verify**: `grep -nE 'docker-compose\.yml|Create this folder|replace <version>' docs/user-manual/laravel-sail.md`
→ no matches.

### Step 6: Smoke test (recommended, with explicit skip path)

If `docker info` succeeds and network is available, build from an extracted
sandbox containing the stub files (`Dockerfile`, `run_turso`, `start-container`
from upstream, `supervisord.conf` from upstream, `php.ini` from upstream,
`sail-entrypoint`, and a tiny `composer.json`/`artisan` if needed) with
`docker build --build-arg WWWGROUP=1000 -t sail-verify:local .`, then run
`docker run --rm sail-verify:local php -m | grep -i libsql` and
`docker run --rm sail-verify:local php -v`.

Expected: the LibSQL module is listed; `php -v` prints a PHP version and exits
without starting supervisord (proving argument forwarding through the new
entrypoint).

If `docker info` fails, network is unavailable, or the build cannot be made
self-contained in one reasonable attempt, record `SKIPPED: <reason>` in the
report. Do not spend more than one retry on infrastructure; the static gates
above are the blocking ones for this plan.

**Verify**: either the two expected outputs above, or an explicit SKIPPED note
with reason.

### Step 7: Re-run all gates

**Verify**: `bash -n` on the extracted `sail-entrypoint` → exit 0;
`vendor/bin/pest --colors=never` → all pass; `git diff --check` → exit 0;
`git status --short` shows only `docs/user-manual/laravel-sail.md` and the
tracker status row.

## Test plan

- No new PHP tests here; plan 001's suite is the regression gate.
- Static gates: upstream drift check, Composer pin availability, `bash -n` on
  the entrypoint, `docker compose config -q`, placeholder grep, and the negative
  greps for root-composer and the host extension mount.
- Optional behavioral smoke: image build + in-container `php -m | grep libsql`
  and argument forwarding. Not run during planning; report honestly.
- These greps become permanent CI checks in plan 004.

## Done criteria

- [ ] Upstream 8.5 Dockerfile re-fetched and matched before editing
- [ ] `ARG PHP_VERSION=8.5` present; no `<version>` token remains in the guide
- [ ] `COPY php.ini` destination uses `${PHP_VERSION}`, not hardcoded `8.3`
- [ ] CLI pinned to a verified stable Composer version; only one install location
      (`/home/sail/.config/composer`); no `/root/.config/composer` reference
- [ ] Extension installed into `/opt/extensions` in the image; no
      `./extensions:/opt/extensions` volume anywhere in the guide
- [ ] `ENTRYPOINT ["/usr/local/bin/sail-entrypoint"]` present, no `CMD` chain
- [ ] `sail-entrypoint` runs `run_turso` then `exec start-container "$@"` and
      passes `bash -n`
- [ ] Compose uses `compose.yaml` naming and passes `PHP_VERSION` as a build arg
- [ ] `docker compose config -q` passes on the extracted stub
- [ ] `vendor/bin/pest --colors=never` exits 0
- [ ] Only `docs/user-manual/laravel-sail.md` and the tracker status row changed
- [ ] `plans/README.md` status row for 003 updated to SELESAI (or `TERHALANG: …`)

## STOP conditions

Stop and report back (do not improvise) if:

- Plan 001 or 002 is not DONE.
- The re-fetched upstream Dockerfile differs materially from the verified facts
  above.
- `composer show darkterminal/turso-php-installer --all` does not list a stable
  `2.2.3` (or the operator has not approved a different pin).
- The pinned Composer install cannot find the matching extension asset for the
  chosen PHP version — that would mean the extension asset coverage claim is
  wrong; report it, do not pick a different PHP version silently.
- A verified upstream fact cannot be reproduced locally (e.g. `curl` blocked).
- Any blocking verification fails twice after a reasonable fix attempt.
- You find you need to change `app/` to make the pinned install succeed.

## Maintenance notes

- This stub intentionally rewrites the whole upstream Dockerfile, so it will
  drift again. Plan 004 mitigates that with real stub files plus a CI check that
  re-fetches upstream and diffs the non-Turso lines. Until plan 004 lands, a
  maintainer should re-run step 1 before each release.
- The `PHP_VERSION` ARG is a deliberate deviation from upstream (which encodes
  the version in the runtime directory name). If upstream switches to its own
  version ARG, drop ours and use theirs — but keep the single-source-of-truth
  property.
- The root/sail HOME split is no longer a correctness issue because boot never
  consults installer metadata. If a future plan reintroduces a runtime `update`,
  that split becomes load-bearing again and must be revisited.
- Deferred deliberately: adding `self-update` to the CLI and host-mounting the
  extension binary. Both were rejected by the operator.
- `docs.yml` (Jekyll build) is the only docs CI today; plan 004 adds the stub
  and link checks. Local Jekyll build is impossible on this machine, so do not
  try.
