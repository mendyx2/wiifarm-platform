# The Wiifarm platform in one container, for a preview site (for example on
# Railway). PHP's built-in server and SQLite on a mounted volume: simple to
# run, fine for testing, not sized for the live site. See deploy/README.md.
# The official PHP image already has every extension the platform uses
# (pdo_sqlite, mbstring, openssl, ...), so no system packages are installed.

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM php:8.3-cli-bookworm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor vendor
COPY --from=assets /app/public/build public/build
RUN composer dump-autoload --optimize --no-dev \
    && rm -f .env bootstrap/cache/*.php public/hot \
    && chmod +x deploy/start.sh

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=sqlite DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=database TRUSTED_PROXIES=* PORT=8080
EXPOSE 8080
CMD ["deploy/start.sh"]
