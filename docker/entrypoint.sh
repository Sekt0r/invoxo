#!/usr/bin/env bash
set -euo pipefail

cd /app

ROLE="${CONTAINER_ROLE:-app}"
LOCK_FILE="/tmp/invoxo-bootstrap.lock"

wait_for_db() {
  if [[ -n "${DB_HOST:-}" ]]; then
    echo "Waiting for DB at ${DB_HOST}:${DB_PORT:-5432}..."
    for i in {1..120}; do
      (echo > /dev/tcp/"${DB_HOST}"/"${DB_PORT:-5432}") >/dev/null 2>&1 && return 0 || true
      sleep 1
    done
    echo "DB not reachable."
    return 1
  fi
  return 0
}

wait_for_vendor() {
  echo "Waiting for /app/vendor/autoload.php..."
  for i in {1..300}; do
    [[ -f /app/vendor/autoload.php ]] && return 0 || true
    sleep 1
  done
  echo "vendor/autoload.php not found."
  return 1
}

wait_for_cache_table() {
  # Requires vendor/autoload.php to exist first.
  echo "Waiting for cache table..."
  for i in {1..180}; do
    php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(Illuminate\Support\Facades\Schema::hasTable('cache')?0:1);" \
      && return 0 || true
    sleep 1
  done
  echo "cache table not found."
  return 1
}

# -----------------------------------------------------------------------------
# Non-app roles: wait only, then run their command
# -----------------------------------------------------------------------------
if [[ "${ROLE}" != "app" ]]; then
  echo "Role=${ROLE}. Waiting for prerequisites..."
  wait_for_db
  wait_for_vendor
  wait_for_cache_table

  exec "$@"
fi

# -----------------------------------------------------------------------------
# APP role: does the bootstrap work (DB wait + composer/npm + migrate)
# -----------------------------------------------------------------------------
wait_for_db

(
  flock -w 600 9

  mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

  if [[ -f composer.json ]]; then
    composer install --no-interaction --prefer-dist
  fi

  if [[ -f package.json ]]; then
    mkdir -p node_modules
    if [[ -z "$(ls -A node_modules 2>/dev/null || true)" ]]; then
      npm ci || npm install
    fi
    if [[ "${NPM_BUILD:-0}" == "1" ]]; then
      npm run build
    fi
  fi

  if id www-data >/dev/null 2>&1; then
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R ug+rwX storage bootstrap/cache || true
  else
    chmod -R 777 storage bootstrap/cache || true
  fi

  if [[ -f artisan ]]; then
    php artisan migrate
    php artisan config:clear || true
    php artisan cache:clear  || true
  fi

) 9>"${LOCK_FILE}"

exec "$@"
