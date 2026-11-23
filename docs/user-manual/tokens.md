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

Add `--fat`, `--roa` or `--pkb` to read individual token secrets.

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
turso-php-installer server:ca-peer-cert-store-get
turso-php-installer server:ca-peer-cert-store-set
```

## sqld commands

```bash
turso-php-installer sqld:env-new <name>
turso-php-installer sqld:env-list
turso-php-installer sqld:env-show <id-or-name>
turso-php-installer sqld:env-edit <id-or-name>
turso-php-installer sqld:env-delete <id-or-name>
turso-php-installer sqld:server-run <id-or-name>
turso-php-installer sqld:open-db <id-or-name>
turso-php-installer sqld:daemon-list
turso-php-installer sqld:daemon-kill
```
