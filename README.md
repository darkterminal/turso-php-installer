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
curl https://darkterminal.github.io/turso-php-installer/builds/turso-php-installer -o ./turso-php-installer
```

Add as a global executable script:

```bash
sudo chmod +x turso-php-installer.phar
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
