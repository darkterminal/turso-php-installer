---
title: sqld local environments
description: Create, run and inspect local sqld environments with the Turso PHP Installer.
section: Local development
eyebrow: Local development
---

The `sqld:*` namespace manages local database environments and the `sqld` daemon lifecycle used for local development.

## Prerequisites

- The stable libSQL PHP extension installed for the running PHP version.
- The `sqld` and `turso` binaries on `PATH` for daemon and shell commands.
- A database token created via `token:create` before an environment can reference a database.
- A controlling terminal for `sqld:open-db`; the command allocates a TTY and has no non-interactive mode.
- Linux or macOS, or Windows through WSL. The `sqld:*` commands do not run on native Windows.

## Commands

### Create a local environment

```bash
turso-php-installer sqld:env-new <name> --variables=<json|dsn> [--force]
```

Creates a local sqld environment.

- `--variables` — JSON object or DSN string; must decode to an object. Example DSN: `DATABASE_TOKEN_ID=1&SQLD_NODE=primary`.
- Without `--variables`, the interactive flow collects database path, node, HTTP listen address, optional gRPC settings and the welcome flag.
- Duplicate environment names are rejected unless `--force` is passed.

### Inspect and delete environments

```bash
turso-php-installer sqld:env-list
turso-php-installer sqld:env-show <name-or-id>
turso-php-installer sqld:env-edit <env-id-or-name>
turso-php-installer sqld:env-delete <name-or-id>
```

### Run the local server

```bash
turso-php-installer sqld:server-run <env-id-or-name> <db-name> [--d|daemon]
```

- Starts the local `sqld` server for the environment.
- `--d|daemon` runs sqld detached; the CLI verifies the forked process is alive before reporting success.

### Open a database shell

```bash
turso-php-installer sqld:open-db <env-id-or-name> <db-name>
```

Runs `turso db shell` against the local server. The command allocates a TTY, so it needs a controlling terminal: it works from an interactive shell but not in CI jobs, cron, or containers started without one (for example `docker exec` without `-t`). There is no non-interactive mode.

### Daemon lifecycle

```bash
turso-php-installer sqld:daemon-list
turso-php-installer sqld:daemon-kill <daemon-pid>
```

## Environment variables

- `SQLD_DB_PATH` — database path. Required by `sqld:server-run`.
- `SQLD_NODE` — `primary`, `replica` or `standalone`.
- `SQLD_HTTP_LISTEN_ADDR` — HTTP listen address, default `127.0.0.1:8080`.
- `SQLD_GRPC_LISTEN_ADDR` / `SQLD_PRIMARY_GRPC_URL` — mutually exclusive primary/replica settings; sqld runs either a primary or a replica, not both.
- `SQLD_NO_WELCOME` — welcome message flag.

## Failure modes

- Missing `SQLD_DB_PATH` in a manually created environment → `sqld:server-run` rejects it before starting sqld, with an error naming the variable and pointing to `sqld:env-new` or `sqld:env-edit`.
- `sqld:open-db` in a non-interactive runner → the shell cannot attach because there is no controlling TTY.
- Any `sqld:*` command on native Windows → exits early with a message directing the user to WSL.
- Both `SQLD_GRPC_LISTEN_ADDR` and `SQLD_PRIMARY_GRPC_URL` set → rejected with a mutual-exclusion error.
- Daemon exits immediately after start → the CLI reports the exit and prints the daemon error log.

Observed during plan 006: daemon start with `--daemon` succeeded, the health endpoint answered `200`, and daemon kill shut the process down cleanly.
