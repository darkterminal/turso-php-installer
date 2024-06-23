# Turso PHP Installer

This is the Turso/libSQL Extension for PHP installer script for Linux and MacOS. Make sure you already have PHP minimal version 8.0.

## Not Support yet
- Laravel Herd Dynamic Extension Installation

## Installation

```bash
curl --proto '=https' --tlsv1.2 -sSf "https://darkterminal.github.io/turso-php-installer/dist/turso-php-installer.phar"
./turso-php-installer.phar
```

Add as a global executable script:

```bash
sudo mv turso-php-installer.phar /usr/local/bin/turso-php-installer
```

## Usage

Install Turso Client PHP / libSQL Extension without worry:
```bash
turso-php-installer install
```

Update Turso Client PHP / libSQL Extension without worry:
```bash
turso-php-installer update
```

Uninstall Turso Client PHP / libSQL Extension without worry:
```bash
turso-php-installer uninstall
```

That's it!

## FAQ

**I am using Windows**

Please, download the official release extension binary from [Turso Client PHP](https://github.com/tursodatabase/turso-client-php/releases) GitHub Release Page. Or you can you WSL or you can use [Turso Docker PHP](https://github.com/darkterminal/turso-docker-php)
