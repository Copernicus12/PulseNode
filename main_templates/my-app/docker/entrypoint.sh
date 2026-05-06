#!/usr/bin/env sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database
touch database/database.sqlite

if [ $# -eq 0 ]; then
    set -- web
fi

case "$1" in
    web)
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
        if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
            php artisan migrate --force
        fi
        exec php artisan serve --host=0.0.0.0 --port=8080
        ;;
    mqtt)
        exec php artisan mqtt:listen
        ;;
    *)
        exec "$@"
        ;;
esac
