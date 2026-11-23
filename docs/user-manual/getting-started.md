---
title: Getting started
description: Install the Turso PHP Installer through Composer, put it on PATH, and install the LibSQL PHP extension.
section: Introduction
eyebrow: First run
---

## Requirements

- PHP >= 8.2 to **run the installer CLI** (`composer.json` requires `^8.2.0`); extension assets cover PHP 8.1, 8.2, 8.3, 8.4 and 8.5 (NTS and TS). To install for PHP 8.1, run the CLI itself on PHP 8.2+
- Composer
- Linux / macOS host (Windows: see the FAQ — manual release download or WSL)

## 1. Install the installer

```bash
composer global require darkterminal/turso-php-installer
```

Add the Composer global bin directory to `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
# or
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

## 2. Run the install

```bash
turso-php-installer install
```

## 3. Verify

```bash
php -m | grep libsql
```

Then in PHP:

```php
<?php

$libsql = new LibSQL("file:example.db");
```

## Next steps

- [Install](/user-manual/install/) — interactive and non-interactive modes
- [Uninstall](/user-manual/uninstall/)
- [Update](/user-manual/update/)
