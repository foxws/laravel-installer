#!/usr/bin/env bash
set -euo pipefail

# The installer runs once and keeps no data, so a fresh key per run is enough...
if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

# The address people open the installer on. On 127.0.0.1 (the default, for
# this machine or an SSH tunnel) it serves plain HTTP. Given a host, such as
# INSTALLER_HOST=192.168.1.100, it serves HTTPS with a certificate from
# Caddy's internal CA, since the link and the inputs then cross the network...
PUBLISHED_PORT="${INSTALLER_PUBLISHED_PORT:-${INSTALLER_PORT}}"

if [ -n "${INSTALLER_HOST:-}" ]; then
    export APP_URL="${APP_URL:-https://${INSTALLER_HOST}:${PUBLISHED_PORT}}"
    export SESSION_SECURE_COOKIE=true
else
    export APP_URL="${APP_URL:-http://127.0.0.1:${PUBLISHED_PORT}}"
fi

php artisan optimize >/dev/null

# Prints the one-time link to open the installer with...
php artisan installer:link

if [ -n "${INSTALLER_HOST:-}" ]; then
    exec frankenphp run --config /etc/installer/Caddyfile --adapter caddyfile
fi

exec frankenphp php-server --listen ":${INSTALLER_PORT}" --root /app/public
