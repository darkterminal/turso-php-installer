---
title: Tokens & local development
description: Create, show, list and delete local libSQL database tokens with the Turso PHP Installer.
section: Local development
eyebrow: Local development
---

Local development helpers live under the `token:*` namespace. They issue short-lived database tokens that the local `sqld` server uses to authenticate database traffic.

## Prerequisites

- The `turso-php-installer` CLI running on PHP 8.2 or newer.
- The stable libSQL PHP extension installed for the running PHP version — the token store is a real `\LibSQL` database, so the extension must be present before any token command runs.

## Commands

### Create a database token

```bash
turso-php-installer token:create <db-name> [--expire=7]
```

Creates a token for the given database name.

- `--expire` — number of days until the token expires. Default: `7`.

The command fails if a token for the same database name already exists.

### Show a database token

```bash
turso-php-installer token:show <db-name> [--fat|--roa|--pkp|--pkb]
```

Prints one of the token secrets:

- `--fat` — full access token.
- `--roa` — read-only access token.
- `--pkp` — public key PEM.
- `--pkb` — public key base64.

### Delete a database token

```bash
turso-php-installer token:delete <db-name> [--all] [--f|force]
```

Deletes the token for a database, or all tokens when `--all` is passed. `--f|force` skips the confirmation prompt.

### List all tokens

```bash
turso-php-installer token:list
```

Lists tokens with their numeric `id`, database name, expiration, creation and update timestamps. The numeric id is the value to use as `DATABASE_TOKEN_ID` when creating a sqld environment.

## Token lifetime and storage

- Default token lifetime is 7 days; override per token with `--expire`.
- Tokens are stored in the installer's token database (a `\LibSQL` database file inside the install directory).
- The full access token is what the local server presents to the database; the read-only token is the safe default for scripts.

## Failure modes

- No installation record → `token:*` commands exit with an error telling the user to install the extension first.

Observed during plan 006: create → list → show → delete round-trips against the real token store all passed, and both JWTs (full and read-only) verified against the stored Ed25519 public key.
