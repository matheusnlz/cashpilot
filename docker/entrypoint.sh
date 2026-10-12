#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"

sed -i -E "s/^Listen [0-9]+$/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
