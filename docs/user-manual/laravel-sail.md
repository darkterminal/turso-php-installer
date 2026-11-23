---
title: Laravel Sail
description: Install the LibSQL PHP extension inside a Laravel Sail container with the Turso PHP Installer.
section: Guides
eyebrow: Guides
---

## Laravel Sail Installation

## Publish Sail configuration files
```bash
sail artisan sail:publish
```

## Folder Structure
```bash
.
├── app
├── artisan
├── bootstrap
├── composer.json
├── composer.lock
├── config
├── database
├── docker # <- Published sail configuration files
├── compose.yaml # <- existing Sail Compose file, edited in place
├── package.json
├── phpunit.xml
├── postcss.config.js
├── public
├── README.md
├── resources
├── routes
├── storage
├── tailwind.config.js
├── tests
├── vendor
└── vite.config.js
```

## Setup Sail

Select your preferred PHP version in the `docker` directory and add the shell
scripts below inside that runtime directory. `PHP_VERSION` is the single version
switch: it is set in the compose `build.args` and every snippet follows it.

Canonical copies of the four Turso-authored files live in
[`stubs/sail/`](https://github.com/darkterminal/turso-php-installer/tree/main/stubs/sail).
The other three files used by the build — `start-container`, `supervisord.conf`
and `php.ini` — are created by `sail artisan sail:publish` from the selected PHP
runtime directory; they are not stored in this repository.

<!-- BEGIN STUB: run_turso -->
```bash
#!/usr/bin/env bash

# Filename: run_turso
#
# Preflight only. The installer CLI version is pinned by Composer at image
# build time and the libSQL extension is owned by the image. Nothing is
# downloaded or updated at boot; a missing extension fails the container.

set -euo pipefail

# Keep this path in sync with the Composer install location in the Dockerfile.
TURSO_INSTALLER_BIN="/home/sail/.config/composer/vendor/bin/turso-php-installer"

if ! php -m | grep -qi '^libsql$'; then
    echo "libSQL extension is not loaded by the container PHP SAPI." >&2
    echo "Check the extension= line and the conf.d drop-in for the selected PHP version." >&2
    exit 1
fi

# Diagnostic only: never parsed and never used as an update trigger.
if [ -x "$TURSO_INSTALLER_BIN" ]; then
    "$TURSO_INSTALLER_BIN" version || true
fi

echo "libSQL extension is loaded."
exit 0
```
<!-- END STUB: run_turso -->

The installer CLI is pinned to a specific version by Composer when the image is
built. The libSQL extension is installed into the image itself and is not
mounted from the host. This boot script only verifies that the extension is
loaded; it does not download, install, or update anything. If the extension is
missing, the container fails to start instead of continuing in a broken state.

Replace the published `Dockerfile` in the selected runtime directory with this one. `php.ini`
comes from `sail artisan sail:publish`; the extension lives in `/opt/extensions`
inside the image.

<!-- BEGIN STUB: Dockerfile.fragment -->
```dockerfile
FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG PHP_VERSION=8.5
ARG NODE_VERSION=24
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=18
ARG PHP_EXTENSIONS=""

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-dev \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mongodb \
        php${PHP_VERSION}-imap \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-ldap \
        php${PHP_VERSION}-msgpack \
        php${PHP_VERSION}-igbinary \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-swoole \
        php${PHP_VERSION}-memcached \
        php${PHP_VERSION}-pcov \
        php${PHP_VERSION}-imagick \
        php${PHP_VERSION}-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && COMPOSER_HOME=/usr/local/share/composer composer global require cpx/cpx:^2.0 \
    && ln -s /usr/local/share/composer/vendor/bin/cpx /usr/local/bin/cpx \
    && curl -fsSL https://deb.nodesource.com/setup_$NODE_VERSION.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm pnpm bun corepack \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    && npx -y playwright install-deps \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get install -y postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN if [ -n "$PHP_EXTENSIONS" ]; then \
    apt-get update \
    && apt-get install -y $(for ext in $PHP_EXTENSIONS; do echo "php${PHP_VERSION}-$ext"; done) \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*; \
fi

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php${PHP_VERSION}

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN git config --global --add safe.directory /var/www/html

# Turso delta: one pinned CLI install, owned by sail, and an image-owned
# extension directory. Nothing is mounted from the host at runtime.
RUN mkdir -p /home/sail/.config/composer /opt/extensions \
    && HOME=/home/sail composer global require darkterminal/turso-php-installer:2.2.3 --working-dir=/home/sail/.config/composer \
    && chmod +x /home/sail/.config/composer/vendor/bin/turso-php-installer \
    && HOME=/home/sail /home/sail/.config/composer/vendor/bin/turso-php-installer install -n --php-version=${PHP_VERSION} --extension-dir=/opt/extensions --php-ini=/etc/php/${PHP_VERSION}/cli/php.ini \
    && chown -R sail:sail /home/sail/.config /opt/extensions

COPY run_turso /usr/local/bin/run_turso
COPY sail-entrypoint /usr/local/bin/sail-entrypoint
RUN chmod +x /usr/local/bin/run_turso /usr/local/bin/sail-entrypoint

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["/usr/local/bin/sail-entrypoint"]
```
<!-- END STUB: Dockerfile.fragment -->

Create one more small script beside the Dockerfile. It runs the preflight first,
then hands arguments back to upstream's `start-container` unchanged.

<!-- BEGIN STUB: sail-entrypoint -->
```bash
#!/usr/bin/env bash
set -euo pipefail
/usr/local/bin/run_turso
exec /usr/local/bin/start-container "$@"
```
<!-- END STUB: sail-entrypoint -->

Filename: `sail-entrypoint`.

Now edit the `compose.yaml` your Laravel app already has — `sail:publish`
rewrites that file, it does not create a new one. Set `PHP_VERSION` in
`build.args`; it drives both the context path and the Dockerfile ARG.

<!-- BEGIN STUB: compose.fragment.yaml -->
```yml
services:
    laravel.test:
        build:
            context: "./docker/${PHP_VERSION:-8.5}"
            dockerfile: Dockerfile
            args:
                WWWGROUP: "${WWWGROUP}"
                PHP_VERSION: "${PHP_VERSION:-8.5}"
        image: "sail-${PHP_VERSION:-8.5}/app"
        extra_hosts:
            - "host.docker.internal:host-gateway"
        ports:
            - "${APP_PORT:-80}:80"
            - "${VITE_PORT:-5173}:${VITE_PORT:-5173}"
        environment:
            WWWUSER: "${WWWUSER}"
            LARAVEL_SAIL: 1
            XDEBUG_MODE: "${SAIL_XDEBUG_MODE:-off}"
            XDEBUG_CONFIG: "${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}"
            IGNITION_LOCAL_SITES_PATH: "${PWD}"
        volumes:
            - ".:/var/www/html"
        networks:
            - sail
networks:
    sail:
        driver: bridge
```
<!-- END STUB: compose.fragment.yaml -->

## Build

Let's sailing!
```bash
sail build --no-cache
sail up
```

## Verify

After the container is up, confirm the extension is loaded by the PHP CLI SAPI:

```bash
sail exec laravel.test php -m | grep libsql
```

Other SAPIs in the container (for example php-fpm) read their own `conf.d`
directories and must be checked separately. The boot preflight above protects
the CLI SAPI only.
