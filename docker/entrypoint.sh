#!/usr/bin/env bash
set -euo pipefail

cd /app

ROLE="${CONTAINER_ROLE:-app}"
LOCK_FILE="/tmp/invoxo-bootstrap.lock"

# Non-app roles: wait for app bootstrap to finish, then run the container command.
if [[ "${ROLE}" != "app" ]]; then
  echo "Role=${ROLE}. Waiting for app bootstrap lock, then starting."
  flock -w 600 "${LOCK_FILE}" true || {
    echo "Timeout waiting for bootstrap lock (${LOCK_FILE})."
    exit 1
  }

  exec "$@"
fi

# -----------------------------------------------------------------------------
# APP role only: everything happens here
# -----------------------------------------------------------------------------

# Wait for Postgres (if configured)
if [[ -n "${DB_HOST:-}" ]]; then
  echo "Waiting for DB at ${DB_HOST}:${DB_PORT:-5432}..."
  for i in {1..60}; do
    (echo > /dev/tcp/"${DB_HOST}"/"${DB_PORT:-5432}") >/dev/null 2>&1 && break || true
    sleep 1
  done
fi

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
