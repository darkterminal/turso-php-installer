---
title: Tokens & local development
description: Create, show, list and delete local libSQL database tokens with the Turso PHP Installer.
section: Local development
eyebrow: Local development
---

## Create a database token

```bash
turso-php-installer token:create <db-name>
```

## Show a database token

```bash
turso-php-installer token:show <db-name>
```

Add `--fat`, `--roa`, `--pkp` or `--pkb` to read an individual token secret.

## Delete a database token

```bash
turso-php-installer token:delete <db-name>
```

## List all tokens

```bash
turso-php-installer token:list
```

## Server commands

```bash
turso-php-installer server:check
turso-php-installer server:ca-cert-create
turso-php-installer server:ca-cert-delete
turso-php-installer server:ca-cert-list
turso-php-installer server:ca-cert-show
turso-php-installer server:ca-peer-cert-create
turso-php-installer server:ca-peer-cert-delete
turso-php-installer server:ca-peer-cert-list
turso-php-installer server:ca-peer-cert-show
turso-php-installer server:cert-store-get
turso-php-installer server:cert-store-set [path]
```

## sqld commands

```bash
turso-php-installer sqld:env-new <name>
turso-php-installer sqld:env-list
turso-php-installer sqld:env-show <name-or-id>
turso-php-installer sqld:env-edit <env-id-or-name>
turso-php-installer sqld:env-delete <name-or-id>
turso-php-installer sqld:server-run <env-id-or-name> <db-name>
turso-php-installer sqld:open-db <env-id-or-name> <db-name>
turso-php-installer sqld:daemon-list
turso-php-installer sqld:daemon-kill <daemon-pid>
```
