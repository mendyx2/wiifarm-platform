#!/bin/sh
# Starts the preview site: prepares the database on the volume, then runs the
# scheduler (nightly runs, emails) and the web server. See deploy/README.md.
set -e
cd "$(dirname "$0")/.."
DATA_DIR="${DATA_DIR:-/data}"
export DB_DATABASE="${DB_DATABASE:-$DATA_DIR/database.sqlite}"

mkdir -p "$DATA_DIR/uploads" storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
# The app's secret key: made once and kept on the volume, unless APP_KEY is set.
if [ -z "$APP_KEY" ]; then
    [ -s "$DATA_DIR/app.key" ] || (umask 077; echo "base64:$(head -c 32 /dev/urandom | base64)" > "$DATA_DIR/app.key")
    export APP_KEY="$(cat "$DATA_DIR/app.key")"
fi
[ -e public/uploads ] || ln -s "$DATA_DIR/uploads" public/uploads
[ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"

php artisan migrate --force
php artisan db:seed --force
php artisan wiifarm:create-admin --from-env
if [ "$APP_DEMO" = "true" ]; then php artisan wiifarm:demo-network; fi
php artisan config:cache && php artisan route:cache && php artisan view:cache

php artisan schedule:work &
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
