#!/bin/sh
set -e

# First start: the volume is empty, so copy the ready-made database in
if [ ! -f "$DB_DATABASE" ]; then
  echo "First start: loading ready-made database"
  cp docker/seed/database.sqlite "$DB_DATABASE"
fi

# Apply any migrations newer than the seed file
php artisan migrate --force

exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
