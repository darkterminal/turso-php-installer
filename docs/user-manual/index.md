---
title: Overview
description: What the Turso PHP Installer is, which release channels it can install, and the commands it exposes.
section: Introduction
eyebrow: Product guide
---

The **Turso PHP Installer** is the Composer package and console CLI that installs the **LibSQL PHP extension** for your PHP application.

It wraps the stable release binaries of the [Turso Client PHP](https://github.com/tursodatabase/turso-client-php) project and wires the correct NTS/TS build into your PHP runtime.

## What the installer does

- Detects the PHP runtime version and picks the right release asset.
- Downloads the prebuilt extension binary for `nts` or `ts`.
- Points the installer at your `php.ini` and extension directory via `--php-ini` and `--extension-dir`.
- Writes an `extension=` line into the selected `php.ini`; each additional SAPI needs its own `conf.d` drop-in and a separate `php -m` check.

## Release channel

| Channel | Source | Use |
|---|---|---|
| Stable | `tursodatabase/turso-client-php` releases | The only channel the installer offers |

The unstable channel was removed. `--unstable` is no longer an option, and an
installation created from it must be uninstalled and reinstalled from stable.

## Command surface

- `install`, `uninstall`, `update`, `version`
- `token:create`, `token:show`, `token:delete`, `token:list`
- `server:*` CA / peer certificate commands
- `sqld:*` local sqld environment and daemon commands

`update` refreshes the **libSQL extension**, not the installer CLI. Sail users
pin the CLI version through the Composer constraint in the Dockerfile.

## Local development workflow

For a complete session — token, optional certificates, sqld environment, server,
shell and cleanup — follow the
[Local development workflow](/user-manual/local-development-workflow/). It shows
the order of commands and links to each namespace reference.

## Next steps

- [Getting started](/user-manual/getting-started/)
- [Install](/user-manual/install/)
- [Update](/user-manual/update/)
- [Uninstall](/user-manual/uninstall/)
- [Local development workflow](/user-manual/local-development-workflow/)
- [Tokens & local development](/user-manual/tokens/)
- [Server certificates](/user-manual/server-certificates/)
- [sqld local environments](/user-manual/sqld/)
- [Laravel Sail](/user-manual/laravel-sail/)
