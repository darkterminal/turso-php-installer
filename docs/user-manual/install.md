---
title: Install
description: "Interactive and non-interactive modes for installing the stable LibSQL PHP extension: NTS and TS builds."
section: Guide
eyebrow: Setup
---

## Interactive mode

```bash
turso-php-installer install
```

## Non-interactive — NTS, stable

```bash
turso-php-installer install -n --php-version=8.3 --php-ini=/etc/php/8.3/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

## Non-interactive — TS, stable

```bash
turso-php-installer install -n --thread-safe --php-version=8.3 --php-ini=/etc/php/8.3/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

Only the stable release channel is available. The unstable channel and the
`--unstable` option were removed; an existing unstable installation must be
uninstalled and reinstalled from stable.

## Help

```bash
turso-php-installer install --help
```
