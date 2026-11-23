---
title: Install
description: "Interactive and non-interactive modes for installing the LibSQL PHP extension: NTS, TS, stable and unstable."
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

## Non-interactive — NTS, unstable

```bash
turso-php-installer install -n --unstable --php-version=8.3 --php-ini=/etc/php/8.3/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

## Non-interactive — TS, stable

```bash
turso-php-installer install -n --thread-safe --php-version=8.3 --php-ini=/etc/php/8.3/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

## Non-interactive — TS, unstable

```bash
turso-php-installer install -n --unstable --thread-safe --php-version=8.3 --php-ini=/etc/php/8.3/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

## Help

```bash
turso-php-installer install --help
```
