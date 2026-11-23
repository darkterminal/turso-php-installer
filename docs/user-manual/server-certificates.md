---
title: Server certificates
description: Check and manage the CA and peer certificates used by the local sqld server.
section: Local development
eyebrow: Local development
---

The `server:*` namespace manages the certificates the local `sqld` server uses for its local API.

## Prerequisites

- `server:check` verifies that `python3` is available and that the `cryptography` Python library can be imported.
- The library must be importable by the Python interpreter the CLI shells out to — either the system interpreter or a dedicated virtual environment.
- Observed during plan 006: without `cryptography` the check exits non-zero with remediation hints; with a virtual environment that has `cryptography` installed the check passes.

```bash
python3 -m venv /path/to/venv
/path/to/venv/bin/pip install cryptography
```

## Commands

### Check server requirements

```bash
turso-php-installer server:check
```

### Inspect the certificate store

```bash
turso-php-installer server:cert-store-get
turso-php-installer server:cert-store-set [path]
```

`server:cert-store-set` overwrites the global certificate store; the default location is the install directory (`certs`).

### CA and peer certificate lifecycle

```bash
turso-php-installer server:ca-cert-create <name=ca> [--expiry=30]
turso-php-installer server:ca-cert-list
turso-php-installer server:ca-cert-show [--raw]
turso-php-installer server:ca-cert-delete <name=ca> [--all]

turso-php-installer server:ca-peer-cert-create <name=ca> [--expiry=30]
turso-php-installer server:ca-peer-cert-list
turso-php-installer server:ca-peer-cert-show
turso-php-installer server:ca-peer-cert-delete
```

- `--expiry` — certificate expiry in days. Default: `30`.
- `server:ca-cert-show --raw` prints the raw certificate and private key.
- `server:ca-cert-delete --all` removes every CA certificate from the global store.

## Failure modes

- Missing Python library → `server:check` exits non-zero and points to the Python environment to install into.

Observed during plan 006: certificate creation with `--expiry=30` produced a certificate that parsed cleanly and expired in 30 days; the store writes certificate and key files under the configured store location.
