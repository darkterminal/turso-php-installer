# Turso PHP Installer Documentation

The **Turso PHP Installer** is the Composer package and Laravel Zero CLI that installs the **LibSQL PHP extension** for PHP applications.

This repository hosts the documentation and user manual for the installer, published as a Jekyll site on GitHub Pages.

## The installer in one line

```bash
composer global require darkterminal/turso-php-installer
turso-php-installer install --php-version=8.3
```

## Where the documentation lives

- [Landing page](https://tursodatabase.github.io/turso-php-installer/)
- [User manual](https://tursodatabase.github.io/turso-php-installer/user-manual/)
- Source: [`turso-client-php`](https://github.com/tursodatabase/turso-client-php)

## Support this Project

<p align="center">
    <a href="https://github.com/sponsors/tursodatabase" target="_blank"><img title="Sponsor Here" src="https://i.imgur.com/R1zU0eW.png" width="100%" /></a>
    via <a href="https://github.com/sponsors/tursodatabase">GitHub Sponsor</a> (Global) / via <a href="https://saweria.co/darkterminal">Saweria</a> (Indonesian)
</p>

---

## Requirements

- PHP >= 8.2 to **run the CLI** (`composer.json` requires `^8.2.0`); install targets are PHP 8.2, 8.3, 8.4 and 8.5 (NTS and TS). PHP 8.1 support was dropped
- Composer
- Linux / macOS host (Windows: manual release download or WSL)

## Install the extension

```bash
composer global require darkterminal/turso-php-installer
export PATH="$HOME/.composer/vendor/bin:$PATH"
turso-php-installer install
```

## Laravel Sail

See [Laravel Sail guide](user-manual/laravel-sail.md).

## Development

```bash
composer install
vendor/bin/pest
bash -n stubs/sail/run_turso stubs/sail/sail-entrypoint
python3 scripts/check_docs.py
```
