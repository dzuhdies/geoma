#!/bin/sh
set -e

echo "Waiting for the database and running migrations..."
tries=0
until php artisan migrate --force; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "Database still unavailable after 30 attempts, giving up"
    exit 1
  fi
  sleep 2
done

exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
