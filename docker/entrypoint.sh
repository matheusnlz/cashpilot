#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"

# Garante que apenas um MPM esteja ativo.
rm -f /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf

a2enmod mpm_prefork >/dev/null 2>&1 || true

sed -i -E "s/^Listen [0-9]+$/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

apache2ctl configtest

exec apache2-foreground
