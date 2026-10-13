# ---------- Stage 1: build frontend assets (Vite + Tailwind) ----------
FROM docker.io/library/node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build

# ---------- Stage 2: install PHP dependencies ----------
FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --prefer-dist --no-interaction --ignore-platform-reqs

# ---------- Stage 3: runtime (FrankenPHP, works with Laravel Octane) ----------
FROM docker.io/dunglas/frankenphp:php8.3 AS runtime

RUN install-php-extensions pcntl pdo_mysql intl zip bcmath opcache

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
             storage/db \
 && chown -R www-data:www-data storage bootstrap/cache public database

ENV XDG_CONFIG_HOME=/tmp XDG_DATA_HOME=/tmp
USER www-data

EXPOSE 8000
CMD ["sh", "docker/entrypoint.sh"]
