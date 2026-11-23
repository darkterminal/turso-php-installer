# Plan 007 — Local development helper workflow documentation

## Goal

Close the documentation gap left by plan 006: the helper namespaces each have
a reference page, but there is no single, ordered workflow that shows how
`token:*`, `server:*` and `sqld:*` fit together in one local development
session. Align all entry points so the new page is discoverable and no public
page points at the old GitHub Pages domain.

## Current state (checked)

- `docs/user-manual/tokens.md`, `server-certificates.md` and `sqld.md` document
  their own namespace: prerequisites, signatures, options, failure modes.
- `docs/user-manual/index.md`, `docs/_data/nav.yml` and `README.md` have no
  workflow entry.
- `docs/_config.yml` has `url: https://tursodatabase.github.io`, but the GitHub
  Pages API reports `https://darkterminal.github.io/turso-php-installer/`.
  `docs/_includes/head.html` builds canonical URLs from `site.url`, so the
  canonical links are wrong.
- `docs/README.md` links to the old `tursodatabase.github.io` domain.
- Signatures and defaults were read from `app/Commands/**` before writing.

## Steps

### Step 1 — Add the workflow page

Create `docs/user-manual/local-development-workflow.md`:

- Prerequisites: installed extension, `sqld` and `turso` binaries, `python3`
  with `cryptography` for certificates, Linux/macOS or WSL, TTY for `open-db`.
- Ordered session: verify install → create token → read token id → create
  environment → run server (foreground or daemon) → health check → open shell
  → stop daemon → clean up environment and token.
- Decision notes: when the certificate path is needed, foreground vs daemon,
  default values that must be overridden, and what breaks in a headless runner.
- Storage map: token DB, environment store, daemon list, sqld data/log files,
  certificate store.
- Failure and recovery table that links to the three namespace pages.
- Exact signatures and defaults only; no invented options or claims.

### Step 2 — Wire discovery

- `docs/_data/nav.yml` — add the workflow page at the top of the Local
  development section.
- `docs/user-manual/index.md` — mention the workflow and add it to Next steps.
- `README.md` — add it to the documentation list.
- `docs/README.md` — fix the two old Pages links.

### Step 3 — Fix the canonical domain

- `docs/_config.yml` — set `url` to `https://darkterminal.github.io`.

### Step 4 — Verify

- `python3 scripts/check_docs.py` prints three `OK:` lines.
- Jekyll build succeeds on CI.
- All links in the new page resolve, including links to the three namespace
  pages.
- No code, workflow, version, tag or release changes.

## Out of scope

- Changing helper behaviour or command signatures.
- Adding new tests (this is a documentation-only change).
- Any release activity.
