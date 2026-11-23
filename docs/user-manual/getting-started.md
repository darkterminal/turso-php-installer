---
title: Getting started
description: Install the Turso PHP Installer through Composer, put it on PATH, and install the LibSQL PHP extension.
section: Introduction
eyebrow: First run
---

## Requirements

- PHP >= 8.2 to **run the installer CLI** (`composer.json` requires `^8.2.0`); install targets are PHP 8.2, 8.3, 8.4 and 8.5 (NTS and TS). PHP 8.1 support was dropped
- Composer
- Linux / macOS host. On Windows, use WSL or download the extension binary manually from the [Turso Client PHP releases](https://github.com/tursodatabase/turso-client-php/releases)

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
