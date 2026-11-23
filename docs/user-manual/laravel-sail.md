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
├── docker-compose.yml # <- Sail Docker Compose
├── extensions # <- Create this folder to store libsql extension binary and stub file
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

Select your preferred PHP Version in `docker` directory and add this `shell` scripts inside the php version directory you choose.
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

The installer CLI is pinned to a specific version by Composer when the image is
built. The libSQL extension is installed into the image itself and is not
mounted from the host. This boot script only verifies that the extension is
loaded; it does not download, install, or update anything. If the extension is
missing, the container fails to start instead of continuing in a broken state.

And modify the `Dockerfile`. Make sure to replace `<version>` placeholder in this file.
```dockerfile
FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=22
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=17

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y wget curl gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano  \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y php<version>-cli php<version>-dev \
    php<version>-pgsql php<version>-sqlite3 php<version>-gd \
    php<version>-curl php<version>-mongodb \
    php<version>-imap php<version>-mysql php<version>-mbstring \
    php<version>-xml php<version>-zip php<version>-bcmath php<version>-soap \
    php<version>-intl php<version>-readline \
    php<version>-ldap \
    php<version>-msgpack php<version>-igbinary php<version>-redis \
    php<version>-memcached php<version>-pcov php<version>-imagick php<version>-xdebug php<version>-swoole \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && npm install -g pnpm \
    && npm install -g bun \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y yarn \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php<version>

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

RUN mkdir -p /root/.config/composer \
    && composer global require darkterminal/turso-php-installer --working-dir=/root/.config/composer \
    && chmod +x /root/.config/composer/vendor/bin/turso-php-installer \
    && mkdir -p /opt/extensions \
    && chmod -R 775 /opt/extensions \
    && /root/.config/composer/vendor/bin/turso-php-installer install -n --php-version=<version> --extension-dir=/opt/extensions --php-ini=/etc/php/<version>/cli/php.ini \
    && chown -R sail:sail /opt/extensions \
    && mkdir -p /home/sail/.config/composer \
    && chown -R sail:sail /home/sail/.config \
    && su sail -c "composer global require darkterminal/turso-php-installer --working-dir=/home/sail/.config/composer" \
    && echo 'export PATH="/home/sail/.config/composer/vendor/bin:$PATH"' >> /home/sail/.bashrc

COPY run_turso /usr/local/bin/run_turso
RUN chmod +x /usr/local/bin/run_turso

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

CMD ["/bin/bash", "-c", "/usr/local/bin/run_turso && start-container"]
```

Then modify the `docker-compose.yml` as you need. Make sure to replace `<version>` placeholder in this file.
```yml
services:
    laravel.test:
        build:
            context: "./docker/<version>"
            dockerfile: Dockerfile
            args:
                WWWGROUP: "${WWWGROUP}"
        image: "sail-<version>/app"
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
            - "./extensions:/opt/extensions"
        networks:
            - sail
networks:
    sail:
        driver: bridge
```

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
