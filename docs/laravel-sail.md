# Laravel Sail Installation

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

VERSION_FILE="/home/sail/.cache/turso_version"
CACHE_DURATION=604800 # 7 days in seconds

get_latest_version() {
    curl -s https://api.github.com/repos/darkterminal/turso-php-installer/tags | \
    grep '"name":' | \
    head -n 1 | \
    sed -n 's/.*"name": "\([0-9.]*\)".*/\1/p'
}

if [ -d "/opt/extensions" ] && [ "$(ls -A /opt/extensions)" ]; then
    CURRENT_VERSION=$(/home/sail/.config/composer/vendor/bin/turso-php-installer | head -n 2 | sed -n 's/.*turso-php-installer[[:space:]]*\([0-9.]*\).*/\1/p')

    if [ -f "$VERSION_FILE" ]; then
        FILE_AGE=$(($(date +%s) - $(stat -c %Y "$VERSION_FILE")))
        if [ "$FILE_AGE" -lt "$CACHE_DURATION" ]; then
            LATEST_VERSION=$(cat "$VERSION_FILE")
        else
            LATEST_VERSION=$(get_latest_version)
            echo "$LATEST_VERSION" > "$VERSION_FILE"
        fi
    else
        mkdir -p "$(dirname "$VERSION_FILE")"
        LATEST_VERSION=$(get_latest_version)
        echo "$LATEST_VERSION" > "$VERSION_FILE"
    fi

    echo "Turso PHP Installer Current Version: $CURRENT_VERSION"
    echo "Turso PHP Installer Latest Version: $LATEST_VERSION"

    if [ "$CURRENT_VERSION" != "$LATEST_VERSION" ]; then
        echo "Updating turso-php-installer to the latest version..."
        /home/sail/.config/composer/vendor/bin/turso-php-installer update
    else
        echo "Turso PHP Installer is up to date."
    fi

    exec "$@"
else
    echo "/opt/extensions is empty or does not exist. Running update..."
    /home/sail/.config/composer/vendor/bin/turso-php-installer update
    exec "$@"
fi
```

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
