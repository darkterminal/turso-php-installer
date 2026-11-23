---
title: Local development workflow
description: Run one complete local development session with the token, server certificate and sqld helpers, from setup to cleanup.
section: Local development
eyebrow: Workflow
---

The helper namespaces work together: `token:*` issues database credentials,
`server:*` manages certificates for the local API, and `sqld:*` creates and runs
the local environment. This page gives one ordered session. Each namespace also
has its own reference page linked at the end.

## Prerequisites

| Requirement | Why | Check |
|---|---|---|
| Stable libSQL PHP extension installed | The token store is a real `\LibSQL` database | `php -m \| grep -i libsql` → `libsql_php` |
| `sqld` and `turso` binaries on `PATH` | Server and shell commands shell out to them | `command -v sqld && command -v turso` |
| `python3` with `cryptography` importable | Only for the `server:*` certificate path | `turso-php-installer server:check` |
| Linux or macOS, or Windows through WSL | `sqld:*` exits early on native Windows | — |
| A controlling terminal | Only for `sqld:open-db`, which allocates a TTY | — |

Verify the CLI itself with `turso-php-installer version`. If the extension is
missing, install it first — see [Install](/user-manual/install/).

## The session at a glance

1. Verify the runtime.
2. Create a database token and read its numeric id.
3. Optionally prepare certificates.
4. Create an sqld environment that references the token id.
5. Start the server in the foreground or as a daemon.
6. Open a database shell.
7. Stop the daemon and clean up.

## Step 1 — Create a database token

```bash
turso-php-installer token:create demo --expire=7
turso-php-installer token:list
```

`token:list` prints the numeric `id`; you need it in Step 3. The full access
token and other secrets can be read with `token:show demo --fat`, `--roa`,
`--pkp` or `--pkb`. Tokens expire after 7 days unless `--expire` is changed.

See [Tokens & local development](/user-manual/tokens/) for all options.

## Step 2 — Prepare certificates (optional)

Use this branch when your sqld setup needs CA and peer certificates for its
local API. `server:check` only validates prerequisites; it does not configure
sqld.

```bash
turso-php-installer server:check
turso-php-installer server:cert-store-get
turso-php-installer server:cert-store-set /path/to/cert-store
turso-php-installer server:ca-cert-create --expiry=30
turso-php-installer server:ca-peer-cert-create --expiry=30
```

`server:cert-store-set` with no path resets the store to the default install
directory. See [Server certificates](/user-manual/server-certificates/) for
inspection, deletion and the Python virtualenv setup.

## Step 3 — Create the sqld environment

```bash
turso-php-installer sqld:env-new demo --variables='{"DATABASE_TOKEN_ID":1,"SQLD_DB_PATH":"/absolute/path/to/demo/data.sqld","SQLD_NODE":"primary","SQLD_HTTP_LISTEN_ADDR":"127.0.0.1:8080"}'
```

- `DATABASE_TOKEN_ID` — the numeric id from `token:list`. An environment that
  points at a deleted token is rejected when it is used.
- `SQLD_DB_PATH` — absolute path is safest; relative values are resolved under
  the install directory.
- `SQLD_NODE` — `primary`, `replica` or `standalone`; default in the interactive
  flow is `primary`.
- `SQLD_HTTP_LISTEN_ADDR` — default `127.0.0.1:8080`.
- `SQLD_GRPC_LISTEN_ADDR` and `SQLD_PRIMARY_GRPC_URL` are mutually exclusive.
- `--force` overwrites an environment with the same name.

Omitting `--variables` starts the interactive flow, which lists existing tokens
and supplies defaults for the database path, node and listen address.

See [sqld local environments](/user-manual/sqld/) for the full variable list.

## Step 4 — Start the server

Foreground, useful while iterating:

```bash
turso-php-installer sqld:server-run demo demo
```

Detached, with a liveness check before the CLI reports success:

```bash
turso-php-installer sqld:server-run demo demo --daemon
turso-php-installer sqld:daemon-list
curl -fsS http://127.0.0.1:8080/health
```

An environment without `SQLD_DB_PATH` is rejected before sqld starts, with an
error naming the variable and suggesting `sqld:env-new` or `sqld:env-edit`.

## Step 5 — Open a database shell

```bash
turso-php-installer sqld:open-db demo demo
```

This runs `turso db shell` against the local server and allocates a TTY. It
works from an interactive terminal, but not in CI, cron, or a container started
without a TTY (`docker exec` without `-t`). There is no non-interactive mode.

## Step 6 — Stop and clean up

```bash
turso-php-installer sqld:daemon-kill <daemon-pid>
turso-php-installer sqld:env-delete demo
turso-php-installer token:delete demo --f
```

Get the pid from `sqld:daemon-list`. Delete the environment before deleting the
token it references; after token deletion the environment can no longer be
used. `token:delete --f` skips the confirmation prompt.

## Defaults worth knowing

| Setting | Default |
|---|---|
| Token expiry (`--expire`) | 7 days |
| Certificate expiry (`--expiry`) | 30 days |
| `SQLD_NODE` in the interactive flow | `primary` |
| `SQLD_HTTP_LISTEN_ADDR` | `127.0.0.1:8080` |
| Primary gRPC listen address | `127.0.0.1:5001` |
| Environment name for CA/peer certs | `ca` |
| Certificate store | `<install-dir>/certs` |

## Where files are written

All paths are relative to the user home directory.

| Content | Location |
|---|---|
| Installation record | `~/.config/turso-client-php/metadata.json` |
| Token database | `~/.config/turso-client-php/tokens.db` |
| Environments | `~/.config/turso-client-php/sqld-environments/environments.json` |
| Daemon list | `~/.config/turso-client-php/sqld-daemon-lists.json` |
| Database and logs | `~/.config/turso-client-php/databases/<env>/` |
| Certificate store (default) | `~/.config/turso-client-php/certs/` |

## Failure and recovery

| Symptom | Likely cause | Action |
|---|---|---|
| `sqld:env-new` says no database tokens found | Token store is empty | Run `token:create` first |
| Environment rejected as referencing a missing token | Token was deleted or recreated | Recreate the environment after `token:create` |
| `sqld:server-run` names `SQLD_DB_PATH` | Manually created environment lacks it | `sqld:env-edit` or recreate with `sqld:env-new` |
| Both gRPC variables rejected | `SQLD_GRPC_LISTEN_ADDR` and `SQLD_PRIMARY_GRPC_URL` set together | Keep only one |
| Daemon dies immediately | Invalid flags, bad address or missing file | Read the `-error.log` in the environment's data directory |
| `sqld:open-db` cannot attach | No controlling TTY | Run it from an interactive terminal |
| `server:check` fails | `cryptography` not importable | Install it in the Python environment used by the CLI |
| Command exits with "Try using WSL" | Native Windows | Use WSL |

## Related pages

- [Tokens & local development](/user-manual/tokens/)
- [Server certificates](/user-manual/server-certificates/)
- [sqld local environments](/user-manual/sqld/)
- [Laravel Sail](/user-manual/laravel-sail/)
