#!/usr/bin/env bash
set -euo pipefail

# The installer runs once and keeps no data, so a fresh key per run is enough...
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

php artisan optimize >/dev/null

# Prints the one-time link to open the installer with...
php artisan installer:link

exec frankenphp php-server --listen ":${INSTALLER_PORT}" --root /app/public
