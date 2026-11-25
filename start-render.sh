#!/bin/sh

set -eu

renderPort="${PORT:-10000}"
php artisan migrate --force --no-interaction
sed -i "s/^Listen 80$/Listen ${renderPort}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${renderPort}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
