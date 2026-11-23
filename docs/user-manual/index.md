---
title: Overview
description: What the Turso PHP Installer is, which release channels it can install, and the commands it exposes.
section: Introduction
eyebrow: Product guide
---

The **Turso PHP Installer** is the Composer package and console CLI that installs the **LibSQL PHP extension** for your PHP application.

It wraps the release binaries of the [Turso Client PHP](https://github.com/tursodatabase/turso-client-php) project — or the unstable line published under `pandanotabear/turso-client-php` — and wires the correct NTS/TS build into your PHP runtime.

## What the installer does

- Detects the PHP runtime version and picks the right release asset.
- Downloads the prebuilt extension binary for `nts` or `ts`.
- Points the installer at your `php.ini` and extension directory via `--php-ini` and `--extension-dir`.
- Writes an `extension=` line into the selected `php.ini`; each additional SAPI needs its own `conf.d` drop-in and a separate `php -m` check.

## Version channels

| Channel | Source | Use |
|---|---|---|
| Stable | `tursodatabase/turso-client-php` releases | Production / default |
| Unstable | `pandanotabear/turso-client-php` releases | Development builds |

## Command surface

- `install`, `uninstall`, `update`, `version`
- `token:create`, `token:show`, `token:delete`, `token:list`
- `server:*` CA / peer certificate commands
- `sqld:*` local sqld environment and daemon commands

`update` refreshes the **libSQL extension**, not the installer CLI. Sail users
pin the CLI version through the Composer constraint in the Dockerfile.

## Next steps

- [Getting started](/user-manual/getting-started/)
- [Install](/user-manual/install/)
- [Laravel Sail](/user-manual/laravel-sail/)
