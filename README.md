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

This is the Turso/libSQL Extension for PHP installer script for Linux and MacOS. Make sure you already have PHP minimal version 8.3.

## Installation

```bash
composer global require darkterminal/turso-php-installer
```

## Laravel Sail

Copy and Paste the stubs from [here](/docs/laravel-sail.md)

----

make sure the installer is available in `PATH` environment variable:

```bash
export PATH="$HOME/.composer/vendor/bin;$PATH"
# or
export PATH="$HOME/.config/composer/vendor/bin;$PATH"
```

## Usage & Overview

```bash
USAGE:  <command> [options] [arguments]

install                    Install Turso libSQL Extension for PHP
uninstall                  Uninstall Turso libSQL Extension for PHP
update                     Update Turso libSQL Extension for PHP
version                    Display Turso PHP Installer version

token:create               Create libSQL Server Database token for Local Development
token:delete               Delete a database token
token:list                 Display all generated database tokens
token:show                 Show libSQL Server Database token for Local Development
```
Unix Only Command
```bash
server:ca-cert-create      Generate CA certificate
server:ca-cert-delete      Delete a CA certificate from the global store location
server:ca-cert-list        List all generated CA certificates
server:ca-cert-show        Show raw CA certificate and private key
server:ca-peer-cert-create Create a peer certificate
server:cert-store-get      Get the cert store location
server:cert-store-set      Set/overwrite global certificate store, to use by the server later. Default is same as {installation_dir}/certs
server:check               Check server requirement, this will check if python3 pip and cyptography lib are installed

sqld:env-delete            Delete an environment by name or ID
sqld:env-edit              Edit an existing environment by ID or name
sqld:env-list              List all created environments
sqld:env-new               Create new sqld environment, save for future use.
sqld:env-show              Show detail of environment
sqld:open-db               Open database using Turso CLI based on environment id or name and database
sqld:server-run            Run sqld server based on environment id or name
```

## Install Extension

**Interactive Mode**

```bash
turso-php-installer install
```

**Non Interactive Mode**

**PHP NTS (Non Thread-Safe) Build**

By default the installer will install the **stable** version of libSQL Client from `tursodatabase/turso-client-php` release with NTS (Non Thread-Safe) build version.
```bash
turso-php-installer install -n --php-vesion=8.3 --php-ini=/etc/php/<version>/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

But, if you want to use the **unstable** version (which is the development) version of libSQL Client, the installer will install from another source `pandanotabear/turso-client-php` release with NTS (Non Thread-Safe) build version. (Btw, Panda is my another pet in GitHub)
```bash
turso-php-installer install -n --unstable --php-vesion=8.3 --php-ini=/etc/php/<version>/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```

**PHP TS (Thread-Safe) Build**

**Stable** version build - libSQL Client from `tursodatabase/turso-client-php` release with TS (Thread-Safe) build version.
```bash
turso-php-installer install -n --thread-safe --php-vesion=8.3 --php-ini=/etc/php/<version>/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
```
**Unstable** version build - libSQL Client, the installer will install from another source `pandanotabear/turso-client-php` release with NTS (Non Thread-Safe) build version.
```bash
turso-php-installer install -n --unstable --thread-safe --php-vesion=8.3 --php-ini=/etc/php/<version>/cli/php.ini --extension-dir=/path/to/your-custom/extensions/directory
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
