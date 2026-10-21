# The installer image. On its own, it asks for an app's git repository and
# clones the chosen branch, tag or commit into the workspace. Apps can also
# build their own installer on top of it, with their "installer/" folder (and
# whatever else they need) baked in, which skips that step:
#
#   FROM ghcr.io/foxws/laravel-installer:2
#   COPY installer/ /app/installer/

ARG FRANKENPHP_VERSION=1-php8.5
ARG NODE_VERSION=26

# Frontend assets
FROM docker.io/library/node:${NODE_VERSION}-slim AS assets

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund

COPY resources ./resources
RUN npm run build

# Installer
FROM docker.io/dunglas/frankenphp:${FRANKENPHP_VERSION}

ENV TZ=UTC
ENV LANG=C.UTF-8

# The Podman client, which runs the steps' commands through the host's Podman
# socket, mounted at /run/podman/podman.sock, "dbus-send", which installs and
# starts the services through the host user's systemd, and git, which fetches
# the app
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates dbus-bin git podman-remote \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN install-php-extensions @composer intl opcache pcntl zip \
    && cp "${PHP_INI_DIR}/php.ini-production" "${PHP_INI_DIR}/php.ini"

# Rootless Podman can't run binaries with file capabilities. libcap2-bin is
# only needed to run setcap, so install and purge it within this layer.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && apt-get purge -y --auto-remove libcap2-bin \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover

# "lpod installer" runs the installer as the host user's own UID
# ("--userns=keep-id"), so the files it writes are theirs and their D-Bus
# session bus accepts it. Whatever UID that is, it needs to write here.
RUN chmod -R a+rwX /app/storage /app/bootstrap/cache /data /config

COPY --chmod=755 runtime/entrypoint.sh /usr/local/bin/installer
COPY runtime/Caddyfile /etc/installer/Caddyfile

# The installer keeps no data, so it runs without a ".env": everything it needs
# comes from here, or from "podman run --env".
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV SESSION_DRIVER=file
ENV CACHE_STORE=file
ENV QUEUE_CONNECTION=sync
ARG INSTALLER_VERSION=dev
ENV INSTALLER_VERSION=${INSTALLER_VERSION}
ENV INSTALLER_PORT=8085
ENV INSTALLER_PODMAN=podman-remote
ENV PODMAN_BINARY=podman-remote
ENV CONTAINER_HOST=unix:///run/podman/podman.sock

EXPOSE 8085

ENTRYPOINT ["/usr/local/bin/installer"]
