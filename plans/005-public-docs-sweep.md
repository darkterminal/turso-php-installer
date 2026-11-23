# Plan 005: Make the public documentation accurate and self-consistent

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving to the next step. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 056da37..HEAD -- README.md docs/ plans/004-stub-ci-gates.md`
> Plan 004 must be DONE first: its link checker is the gate for this plan, and
> the two Sail links are already fixed there.

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: plans/004-stub-ci-gates.md
- **Category**: docs
- **Planned at**: commit `056da37`, 2026-11-23

## Why this matters

The site and READMEs currently contradict each other and the CLI. The landing
page points at a different GitHub organisation than the repo's own remote; the
docs say PHP 8.1 is supported while `composer.json` requires `^8.2` to even run
the installer; the overview claims nothing is registered by hand even though the
installer appends an `extension=` line and the Sail guide copies a `php.ini`
drop-in; the guide invokes `sail` without explaining it is `./vendor/bin/sail`;
and there is no in-container verification or rollback path. These are not
cosmetic: a reader following them can end up on a 404, on a PHP version the CLI
cannot run, or with an extension that loaded for the wrong SAPI.

This plan is the final prose sweep. All technical decisions were made in plans
001–004; this plan only aligns wording, links and examples with them.

## Current state

Verified at commit `056da37` (line numbers may shift after plans 002–004 — use
the quoted text as the search key, not the number):

| Location | Current text / fact | Problem |
|---|---|---|
| `docs/index.html:55,59,232,258-259,265-267` and `docs/README.md:16-18` | URLs to `tursodatabase/turso-php-installer` | Canonical remote is `darkterminal/turso-php-installer` (`git remote -v`, `composer.json:8-9`) |
| `docs/index.html:266` | link to `CONTRIBUTING.md` | No such file at repo root |
| `docs/index.html:257` | label "API reference" pointing at the overview | Mislabelled destination |
| `README.md:13` | "PHP minimal version 8.2" | Correct for CLI runtime |
| `README.md:15`, `docs/README.md:31`, `docs/index.html:166,204`, `docs/user-manual/getting-started.md:10` | assets cover PHP 8.1–8.5, NTS and TS | Correct for extension assets, but not distinguished from CLI runtime |
| `composer.json:30` | `"php": "^8.2.0"` | CLI cannot run on 8.1 |
| `app/Traits/HasInstallQuestions.php:113-119` | picker offers `8.1`, `8.2`, `8.3`, `8.4`, `8.5` | Choosing 8.1 leads to a runtime failure the docs never explain |
| `docs/user-manual/index.md:17` | "register nothing by hand" | `BaseInstaller.php:279-282,416-442` appends an `extension=` line; Sail guide copies `php.ini` |
| `docs/user-manual/getting-started.md:34-38` | `php -m | grep libsql` for the host only | No container/SAPI verification |
| `docs/user-manual/laravel-sail.md:212-218` | ends at `sail up` | No rollback, though a plan-002/003 change modifies Dockerfile, compose and host files |
| `docs/user-manual/update.md:12` | describes the extension update | The word "update" is easy to confuse with updating the CLI; the Sail gate used to do exactly that |
| `docs/README.md:49-51` | only `composer install` and `vendor/bin/pest` as dev commands | Plan 004 added `python3 scripts/check_docs.py` and the stub lint commands |

Decisions from the operator that must be reflected, not re-litigated:
- CLI is pinned via Composer in the Dockerfile; no `self-update` command.
- Extension is owned by the image; no host bind mount of binaries.
- Only thin Turso-owned stubs live in this repo; upstream Sail files are
  published by `sail:publish`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Docs checks | `python3 scripts/check_docs.py` | exit 0, three `OK:` lines |
| Stub sync | `bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint` | exit 0 |
| Tests | `vendor/bin/pest --colors=never` | all pass |
| Links by hand | `grep -rn 'tursodatabase/turso-php-installer' README.md docs/` | no matches after step 2 |
| Placeholder | `grep -rn '<version>' README.md docs/ stubs/` | no matches |
| Git state | `git status --short -b` | only in-scope files changed |

`jekyll` is not installed locally. Link correctness is proven by
`python3 scripts/check_docs.py` (offline, file-based) and by the existing
`.github/workflows/docs.yml` Jekyll build in CI. Do not try to install Jekyll.

## Scope

**In scope**:
- `README.md`
- `docs/README.md`
- `docs/index.html`
- `docs/_data/nav.yml` (only if a link target needs correcting)
- `docs/user-manual/index.md`
- `docs/user-manual/getting-started.md`
- `docs/user-manual/update.md`
- `docs/user-manual/laravel-sail.md` — add prerequisite, verification and
  rollback prose only (do not touch the four marker-wrapped stub blocks)
- `CONTRIBUTING.md` (create) **or** remove the dead link, per step 3

**Out of scope** (do NOT touch, even though they look related):
- `stubs/sail/*`, `scripts/check_docs.py`, `.github/workflows/*` — plan 004
  owns them. If a doc change requires a checker change, STOP and report.
- `app/`, `composer.json`, `composer.lock`, `builds/`, `build.sh`, `CHANGELOG.md`.
- `plans/README.md` beyond its status row.

## Git workflow

- Follow `plans/WORKFLOW.md` (standing workflow). Create branch
  `plan/005-public-docs-sweep`; never work directly on `main`.
- Issue `Plan 005 — ...` must exist BEFORE the branch is created. Put
  `Refs #<issue>` in every commit body and `Closes #<issue>` in the PR body,
  together with this plan path.
- Commit message style: `docs: align PHP support matrix and Sail prerequisites`.
- Open a PR against `main`, run every verification gate, double-check done
  criteria and CI, then squash-merge only when clean. Update the status row in
  `plans/README.md` in a small follow-up commit on `main`.

## Steps

### Step 1: Add one authoritative PHP support table

In `README.md`, replace the two scattered version sentences (`:13` and `:15`)
with a short table:

| Item | Requirement |
|---|---|
| PHP needed to **run the installer CLI** | 8.2 or newer (`composer.json` requires `^8.2.0`) |
| PHP versions with **prebuilt extension assets** | 8.1, 8.2, 8.3, 8.4, 8.5 (NTS and TS) |
| How the installer selects an asset | Reads the latest GitHub release metadata and matches the target PHP version |

Add a note: choosing 8.1 as the install target requires running the CLI itself
on PHP 8.2+ (for example, via a different PHP binary or another machine).

Mirror the same distinction in `docs/README.md:31`,
`docs/user-manual/getting-started.md:10` and `docs/index.html:204`. Keep the
numbers identical everywhere — the checker does not verify prose numbers, so
the executor must grep them manually.

**Verify**: `grep -rn 'PHP 8.1\|8\.1–8\.5\|8\.1 - 8\.5\|minimal version' README.md docs/`
→ every occurrence either names 8.1 as an *asset target* or explicitly pairs it
with the CLI 8.2+ requirement.

### Step 2: Align repository URLs

Replace all `tursodatabase/turso-php-installer` occurrences with
`darkterminal/turso-php-installer` in `docs/index.html` and `docs/README.md`.
Do not change URLs that legitimately point to `tursodatabase/turso-client-php`
(that is a different repository and is the source of the extension binaries).

**Verify**: `grep -rn 'tursodatabase/turso-php-installer' README.md docs/` → no
matches; `grep -rn 'tursodatabase/turso-client-php' docs/` → still has matches.

### Step 3: Fix the dead contribution/API links

For `docs/index.html:266` pointing at `CONTRIBUTING.md`, choose the smaller
honest fix: **remove the link** and keep only the issue-tracker link, unless the
operator has previously asked for a contribution guide. Do not invent a large
new policy file. For `docs/index.html:257` ("API reference"), relabel it to
"User manual" or remove it if it duplicates the overview entry.

**Verify**: `python3 scripts/check_docs.py` → exit 0; `grep -n 'CONTRIBUTING' docs/index.html` → no matches (if removed).

### Step 4: Add prerequisites and the `sail` alias explanation

At the top of `docs/user-manual/laravel-sail.md`, before the first `sail
artisan sail:publish` command, add:

- The guide assumes Sail is already installed in the Laravel app.
- `sail` in the examples means `./vendor/bin/sail`; either use that path or set
  an alias. Give the alias from the current Laravel docs:
  `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'`.
- `sail:publish` publishes `docker/<version>/` and rewrites the app's existing
  `compose.yaml`; it does not create the compose file or publish the `sail`
  binary.

**Verify**: `grep -n 'vendor/bin/sail' docs/user-manual/laravel-sail.md` → at
least one match; `python3 scripts/check_docs.py` → exit 0.

### Step 5: Add verification and rollback sections

Append to `docs/user-manual/laravel-sail.md` after the build section:

**Verify**: a `sail exec laravel.test php -m | grep libsql` command, with a note
that other SAPIs (e.g. php-fpm) need their own conf.d drop-in and must be checked
separately. This complements the boot-time preflight added in plan 002.

**Roll back**: list exactly what to undo, in order: remove the four Turso lines
from the published `Dockerfile` (or restore it from `sail:publish`), remove
`PHP_VERSION` from `compose.yaml` `build.args`, remove the `run_turso` /
`sail-entrypoint` files from `docker/<php>/`, rebuild with `sail build
--no-cache`, and run the CLI `uninstall` command if the extension was also
installed on the host. Because plan 003 removed the host extension mount, no
host binary directory needs deleting.

**Verify**: `grep -nE '^## (Verify|Rollback)' docs/user-manual/laravel-sail.md`
→ both headings present; `python3 scripts/check_docs.py` → exit 0.

### Step 6: Clarify what "update" means

In `docs/user-manual/update.md`, add a one-line note near the top: this command
updates the **libSQL extension**, not the installer CLI; the CLI version is
pinned by the Composer constraint in the Dockerfile for Sail users. Mirror that
sentence in `docs/user-manual/index.md` where it describes the command suite.

Also fix the "registers nothing by hand" claim at
`docs/user-manual/index.md:17`: state that the CLI writes an `extension=`
line into the selected `php.ini`, and that each additional SAPI needs its own
drop-in and verification.

**Verify**: `grep -rn 'not the installer CLI' docs/user-manual/` → at least one
match; `grep -rn 'nothing by hand\|nothing is registered by hand' docs/` → no
matches.

### Step 7: Refresh the development commands

In `docs/README.md`, add the commands introduced by plan 004 under
`## Development`:

```bash
composer install
vendor/bin/pest
bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint
python3 scripts/check_docs.py
```

**Verify**: `python3 scripts/check_docs.py` → exit 0, including the link check
over the newly referenced paths.

### Step 8: Final gates

**Verify**: all table commands pass; `vendor/bin/pest --colors=never` → all
pass; `git diff --check` → exit 0; `git status --short` shows only in-scope
files plus tracker status rows.

## Test plan

- No PHP tests. Documentation verification is the offline checker plus manual
  grep assertions above.
- Run `python3 scripts/check_docs.py` after **every** step, not just at the end,
  so a broken link is attributed to the step that introduced it.
- The Jekyll build remains a CI-only gate via `.github/workflows/docs.yml`;
  report it as "not run locally" rather than claiming it passed.

## Done criteria

- [ ] One PHP support table exists in README.md and the same distinction is
      repeated in docs/README.md, getting-started.md and index.html
- [ ] No `tursodatabase/turso-php-installer` URL remains; genuine
      `turso-client-php` URLs remain
- [ ] Dead `CONTRIBUTING.md` / mislabelled "API reference" links resolved
- [ ] Sail guide explains `sail` = `./vendor/bin/sail` and what `sail:publish`
      actually publishes
- [ ] Sail guide has `## Verify` and `## Rollback` sections
- [ ] `update.md` distinguishes extension updates from CLI pinning
- [ ] "registers nothing by hand" claim corrected
- [ ] `docs/README.md` lists the plan-004 verification commands
- [ ] `python3 scripts/check_docs.py` exits 0 with three `OK:` lines
- [ ] `vendor/bin/pest --colors=never` exits 0
- [ ] Only in-scope files and the tracker status row changed
- [ ] `plans/README.md` status row for 005 updated to SELESAI (or `TERHALANG: …`)

## STOP conditions

Stop and report back (do not improvise) if:

- Plan 004 is not DONE or `python3 scripts/check_docs.py` is red before editing.
- The docs checker fails on a link outside the in-scope files — that means a
  checker bug or an out-of-scope file needs changing.
- You decide a `CONTRIBUTING.md` file is genuinely required; that is a new
  policy document and needs operator approval rather than an improvised draft.
- The PHP support numbers in code (`composer.json`, `HasInstallQuestions.php`)
  disagree with the numbers in this plan — report the mismatch; do not change code.
- Any verification command fails twice after a reasonable fix attempt.

## Maintenance notes

- The PHP support table is duplicated in four places by design (README, docs
  README, getting-started, landing page). If asset coverage changes, update all
  four; consider adding a checker rule later if it drifts again.
- The repository URL mismatch may reflect an abandoned org migration. If the
  operator confirms a move to `tursodatabase/turso-php-installer`, reverse step 2
  and update `composer.json`, `git remote` and plan metadata in a separate,
  authorized change.
- Deferred from the original audit: hardening the metadata-to-sudo path in
  `BaseInstaller.php:444-451` / `app/helpers.php:231-240`. It is installer-global,
  not a docs concern.
- Jekyll rendering was not verified locally; `.github/workflows/docs.yml` is
  the authority after this lands.
