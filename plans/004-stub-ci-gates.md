# Plan 004: Turn the Sail snippets into real stub files and gate them in CI

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving to the next step. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`.
>
> **Drift check (run first)**: `git diff --stat 056da37..HEAD -- docs/ README.md .github/workflows/ plans/003-upstream-runtime-alignment.md`
> Plans 001–003 must be DONE first. This plan turns their output into canonical
> artifacts; doing it earlier would freeze broken content.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: plans/003-upstream-runtime-alignment.md
- **Category**: dx
- **Planned at**: commit `056da37`, 2026-11-23

## Why this matters

Right now all four files a Sail user must create exist only as fenced markdown
blocks inside `docs/user-manual/laravel-sail.md`. The guide tells the reader to
`COPY run_turso`, `COPY start-container`, `COPY supervisord.conf` and
`COPY php.ini` without saying where those files come from (`laravel-sail.md:168-173`),
and no tool can lint or test them. That is how the runtime drifted for almost two
years without a single red check. This plan promotes the Turso-authored pieces
to real files, keeps the upstream-owned pieces owned by `sail:publish`, and adds
a CI job that fails on shell syntax errors, unresolved placeholders, stub/doc
drift, and broken documentation links.

## Current state

- `docs/user-manual/laravel-sail.md` — after plan 003 it contains four relevant
  fenced blocks: `run_turso` (bash), the Dockerfile (dockerfile), the compose
  file (yml), and the new `sail-entrypoint` (bash). There are no marker comments
  around them.
- `docs/user-manual/laravel-sail.md:168-173` — `COPY` of `run_turso`,
  `start-container`, `supervisord.conf`, `php.ini` with no sourcing explanation.
- `README.md:25` and `docs/README.md:45` — link to `/docs/laravel-sail.md`,
  which does not exist. The real page is `docs/user-manual/laravel-sail.md`,
  served at `/user-manual/laravel-sail/` per `docs/_data/nav.yml:19-20`.
- `.github/workflows/docs.yml` — builds Jekyll on push to `main` for `docs/**`
  only. It cannot catch broken markdown links or bad shell syntax.
- Plan 001 added `.github/workflows/tests.yml`; this plan adds a separate docs
  checks workflow rather than mixing concerns.
- `python3` is available locally (`/home/linuxbrew/.linuxbrew/bin/python3`) and
  on GitHub runners. `shellcheck` and `jekyll` are not available locally and
  must not be introduced.
- The operator rejected copying the whole Sail runtime into this repo. Only
  Turso-authored files are canonical here; `php.ini`, `start-container` and
  `supervisord.conf` continue to come from `sail artisan sail:publish`.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Shell syntax | `bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint` | exit 0 |
| Docs checks | `python3 scripts/check_docs.py` | exit 0, prints "OK" lines |
| Placeholder gate | `grep -rn '<version>' stubs/ docs/user-manual/laravel-sail.md` | no matches |
| Compose shape | `docker compose -f stubs/sail/compose.fragment.yaml config -q` | exit 0 after variable substitution |
| Git state | `git status --short -b` | only in-scope files changed |

Note: `compose.fragment.yaml` contains `${...}` variables; `docker compose
config -q` resolves them from the environment with empty defaults, which is
sufficient for a syntax check. If it errors on an unset variable, set
`WWWGROUP=1000 WWWUSER=1000 PHP_VERSION=8.5 PWD=/tmp` in front of the command.

## Scope

**In scope**:
- `stubs/sail/` (create): `run_turso`, `sail-entrypoint`, `Dockerfile.fragment`,
  `compose.fragment.yaml`, `README.md`
- `scripts/check_docs.py` (create)
- `.github/workflows/docs-checks.yml` (create)
- `docs/user-manual/laravel-sail.md` — add marker comments around the four
  fenced blocks and add the "where do the other files come from" paragraph
- `README.md:25` and `docs/README.md:45` — fix only the two Sail links so the new
  link check starts green (the rest of the docs sweep is plan 005)
- `plans/README.md` status rows (004, and optionally 005 if a link was fixed early)

**Out of scope** (do NOT touch, even though they look related):
- `php.ini`, `start-container`, `supervisord.conf` — upstream-owned; do not copy
  them into this repo.
- Any other documentation text, PHP-version table, repository URLs
  (`docs/index.html`), or `CONTRIBUTING.md` — plan 005.
- `app/`, `composer.json`, `composer.lock`, `builds/`, `build.sh`.
- Adding `shellcheck`, `jekyll`, `lychee` or any other new dependency.

## Git workflow

- Follow `plans/WORKFLOW.md` (standing workflow). Create branch
  `plan/004-stub-ci-gates`; never work directly on `main`.
- Issue `Plan 004 — ...` must exist BEFORE the branch is created. Put
  `Refs #<issue>` in every commit body and `Closes #<issue>` in the PR body,
  together with this plan path.
- Commit message style: `ci: lint Sail stubs and check documentation links`.
- Open a PR against `main`, run every verification gate, double-check done
  criteria and CI, then squash-merge only when clean. Update the status row in
  `plans/README.md` in a small follow-up commit on `main`.

## Steps

### Step 1: Create the canonical stub files

Create `stubs/sail/` and copy the four blocks from
`docs/user-manual/laravel-sail.md` **verbatim** (post-plan-003 content) into:

- `stubs/sail/run_turso` — the preflight from plan 002
- `stubs/sail/sail-entrypoint` — the two-line wrapper from plan 003
- `stubs/sail/Dockerfile.fragment` — the complete Dockerfile block from plan 003
- `stubs/sail/compose.fragment.yaml` — the complete compose block from plan 003

`Dockerfile.fragment` is named "fragment" but currently holds the full Dockerfile;
that is intentional. It keeps the guide copy-pasteable while the repo owns a
lintable artifact. Do not split it into overlays in this plan.

**Verify**: `bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint` → exit 0;
`wc -l stubs/sail/*` → each file non-empty.

### Step 2: Add marker comments and a sourcing paragraph to the guide

Wrap each of the four fenced blocks in the doc with markers on their own lines:

```
<!-- BEGIN STUB: run_turso -->
```bash
...
```
<!-- END STUB: run_turso -->
```

Use exactly these stub names: `run_turso`, `sail-entrypoint`, `Dockerfile.fragment`,
`compose.fragment.yaml`. Markers must be unique in the file.

Add a paragraph before the `run_turso` block stating: canonical copies of the
Turso-authored files live in `stubs/sail/` in the repository; the other three
files (`start-container`, `supervisord.conf`, `php.ini`) are created by
`sail artisan sail:publish` from the selected PHP runtime directory. Give the
canonical URL `https://github.com/darkterminal/turso-php-installer/tree/main/stubs/sail`.

**Verify**: for each of the four names,
`grep -c '<!-- BEGIN STUB: NAME -->' docs/user-manual/laravel-sail.md` → `1`,
and the matching `END` count is also `1`.

### Step 3: Write the docs checker

Create `scripts/check_docs.py` using Python 3 stdlib only. It must perform three
checks and print one `OK: <check>` line per passing check, exiting 1 on the first
failure:

1. **Stub sync** — for each of the four stub names, extract the lines between
   its BEGIN/END markers from `docs/user-manual/laravel-sail.md` (excluding the
   marker lines themselves, but including the fenced-code lines), strip exactly
   one trailing newline, and compare to the contents of `stubs/sail/<name>`.
   Use `difflib.unified_diff` to print a readable diff on mismatch. Note the
   canonical files do not include the ``` fences while the doc blocks do, so
   compare only the lines *inside* the fence: drop the first and last line of
   the extracted region. Document that rule in a comment in the script.
2. **Placeholders** — fail if the regex `<[A-Za-z_][A-Za-z0-9_-]*>` occurs in
   any file under `stubs/` or in `docs/user-manual/laravel-sail.md`. Also fail
   if the literal `/etc/php/8.3/` occurs there. `${PHP_VERSION}` and
   `${PHP_VERSION:-8.5}` are allowed.
3. **Links** — scan `README.md` and all `*.md` files under `docs/`. Extract
   markdown link targets with `re.findall(r'\[[^\]]*\]\(([^)]+)\)', text)`.
   Skip targets starting with `http://`, `https://`, `mailto:`, `#`, or empty.
   Strip any `#anchor`. Resolve:
   - absolute Jekyll path starting `/`: strip leading `/`, strip optional
     `turso-php-installer/` prefix (from `baseurl` in `docs/_config.yml`), then
     try `docs/<path>`, `docs/<path>.md`, `docs/<path>/index.md`;
   - relative path: resolve against the linking file's directory, then try the
     same `.md` / `/index.md` fallbacks.
   Report every unresolved target with `file:line`. Do not attempt network
   requests.

**Verify**: `python3 scripts/check_docs.py` → exit 0 and three `OK:` lines. If
the link check fails on the two known broken Sail links, fix them in step 4 and
re-run; if it fails on any other link, STOP and report (plan 005 owns those).

### Step 4: Fix the two Sail links

- `README.md:25`: `/docs/laravel-sail.md` → `docs/user-manual/laravel-sail.md`
- `docs/README.md:45`: `/docs/laravel-sail.md` → `user-manual/laravel-sail.md`

These are repo-relative links, which work both on GitHub and through the local
check. Do not change any other link in this plan.

**Verify**: `python3 scripts/check_docs.py` → exit 0 with three `OK:` lines.

### Step 5: Wire the checks into CI

Create `.github/workflows/docs-checks.yml`, triggered on push and pull_request
to `main` with paths `docs/**`, `README.md`, `stubs/**`, `scripts/check_docs.py`,
and this workflow. Use `actions/checkout@v6`, `actions/setup-python@v5` with
`python-version: '3.12'`, then:
- `bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint`
- `python3 scripts/check_docs.py`

Do not add `shellcheck`, Pint, or a Jekyll build here — `docs.yml` already owns
the Jekyll build.

**Verify**: `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/docs-checks.yml'))"`
→ exit 0 (PyYAML may not be installed locally; if so, skip with a note and rely
on `git diff --check` plus visual indentation review). `git diff --check` → exit 0.

### Step 6: Add the stub README

Create `stubs/sail/README.md` (short, public-friendly) explaining: what each
file is; that `Dockerfile.fragment` and `compose.fragment.yaml` are the full
contents to paste after running `sail:publish`; that `start-container`,
`supervisord.conf` and `php.ini` are upstream-owned and must not be copied here;
and that `PHP_VERSION` in compose is the single version switch. Include the
commands from the "Commands you will need" table for local verification.

**Verify**: `python3 scripts/check_docs.py` still exits 0 (the new README may
contain links; use repo-relative paths only).

### Step 7: Final gates

**Verify**: all commands in the table pass; `vendor/bin/pest --colors=never`
→ all pass; `git status --short` shows only in-scope files plus tracker status
rows.

## Test plan

- No PHP tests. The new gate is `python3 scripts/check_docs.py`, which is itself
  deterministic and offline.
- Negative self-check (do once, then revert): introduce a deliberate typo in
  `stubs/sail/run_turso`, run the checker, confirm it exits non-zero and prints a
  diff; revert the typo, confirm green again. Do not leave the typo in the tree.
- Negative link check (do once, then revert): change the Sail link in README to
  a nonexistent path, confirm the checker reports it with `file:line`, revert.

## Done criteria

- [ ] `stubs/sail/` contains exactly `run_turso`, `sail-entrypoint`,
      `Dockerfile.fragment`, `compose.fragment.yaml`, `README.md`
- [ ] None of `php.ini`, `start-container`, `supervisord.conf` were copied into
      the repo
- [ ] Four unique BEGIN/END marker pairs exist in `docs/user-manual/laravel-sail.md`
- [ ] `python3 scripts/check_docs.py` exits 0 with three `OK:` lines
- [ ] `bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint` exits 0
- [ ] `grep -rn '<version>' stubs/ docs/user-manual/laravel-sail.md` returns nothing
- [ ] `.github/workflows/docs-checks.yml` exists and runs both checks on push/PR
- [ ] The two Sail links in `README.md` and `docs/README.md` are fixed; no other
      doc text changed in this plan
- [ ] `vendor/bin/pest --colors=never` exits 0
- [ ] `plans/README.md` status row for 004 updated to SELESAI (or `TERHALANG: …`)

## STOP conditions

Stop and report back (do not improvise) if:

- Plan 003 is not DONE, or the four blocks in the doc differ from what plan 003
  describes.
- The link checker fails on a link other than the two known Sail links — those
  belong to plan 005.
- The stub-sync check cannot be made to pass without duplicating fences in the
  canonical files; report the exact diff instead of weakening the check.
- `python3` is unavailable in a situation where the check must run locally.
- Any verification command fails twice after a reasonable fix attempt.

## Maintenance notes

- Adding a new Sail stub requires three edits: create the file, add a marker
  pair in the guide, and add its name to the stub list in
  `scripts/check_docs.py`. Leaving out the third silently disables the check.
- If upstream Sail changes its published `php.ini`/`start-container` layout,
  plan 003's upstream re-fetch gate must be re-run; this plan's checker only
  protects Turso-owned files.
- `docs.yml` still owns Jekyll builds. Keep `docs-checks.yml` focused on static
  checks so a broken link does not block page deployment without explanation.
- Deferred deliberately: `shellcheck` (not installed locally), a real `sail
  build` smoke in CI (needs Docker + network and is slow), and full link fixing
  beyond the two Sail links (plan 005).
