<p align="center">
  <a href="https://discord.gg/turso">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://i.imgur.com/UhuW3zm.png">
      <source media="(prefers-color-scheme: light)" srcset="https://i.imgur.com/vljWbfr.png">
      <img alt="Shows a black logo in light color mode and a white one in dark color mode." src="https://i.imgur.com/vGCC0I4.png">
    </picture>
  </a>
</p>

# Turso PHP Installer

This is the Turso/libSQL Extension for PHP installer script for Linux and MacOS. Make sure you already have PHP minimal version 8.0.

## Not Support yet

- Laravel Herd Dynamic Extension Installation

## Installation

```bash
composer global require darkterminal/turso-php-installer
```

make sure the installer is available in `PATH` environment variable:

```bash
export PATH="$HOME/.composer/vendor/bin;$PATH"
# or
export PATH="$HOME/.config/composer/vendor/bin;$PATH"
```

## Usage & Overview

```bash
USAGE:  <command> [options] [arguments]

install      Install Turso libSQL Extension for PHP
uninstall    Uninstall Turso libSQL Extension for PHP
update       Update Turso libSQL Extension for PHP
version      Display Turso PHP Installer version

token:create Create libSQL Server Database token for Local Development
token:show   Show libSQL Server Database token for Local Development
```

## Install Extension

**Interactive Mode**

```bash
turso-php-installer install
```

**Non Interactive Mode**
```bash
turso-php-installer install -y --php-vesion=8.3 --php-ini-file=/etc/php/<version>/cli/php.ini --ext-destionation=/path/to/your-custom/extensions/directory
```
> See `turso-php-installer install --help`

## Uninstall Extension

```bash
turso-php-installer uninstall
```

## Update Extension

```bash
turso-php-installer update
```

## Using Local LibSQL for Development

**Create Database Token**

Create libSQL Server Database token for Local Development
```bash
turso-php-installer token:create
```

**Show Database Token**

Show libSQL Server Database token for Local Development
```bash
turso-php-installer token:show
```
This command will show all tokens and secrets. You can also see indivial token read at `turso-php-installer token:show --help`

> [See detail explanation](https://gist.github.com/darkterminal/c272bf2a572bc5d7378f31cf4aea5f19)

## FAQ

**The Extension Is Not Working in Windows**

Please, download the official release extension binary from [Turso Client PHP](https://github.com/tursodatabase/turso-client-php/releases) GitHub Release Page. Or you can you WSL or you can use [Turso Docker PHP](https://github.com/darkterminal/turso-docker-php)

## Give Me a Coffe

- GitHub Sponsors
- Direct Paypal
