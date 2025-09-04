#!/bin/sh

set -e

APP_BASE_DIR="${APP_BASE_DIR:-/var/www/html}"

if [ -z "${APP_KEY:-}" ] && [ ! -f "${APP_BASE_DIR}/.env" ]; then
  echo "⚠️ WARNING: Missing APP_KEY and .env file; generating new one. Set APP_KEY or mount .env with APP_KEY to persist."
  echo "APP_KEY=" >> "${APP_BASE_DIR}/.env"
  echo "Generating application key..."
  if [ -f "${APP_BASE_DIR}/artisan" ]; then
    php artisan key:generate --force
  else
    echo "❌ Artisan command not found; unable to generate key."
    exit 1
  fi
  echo "✅ App key generated."
else
  echo "ℹ️ APP_KEY already set or .env file exists; skipping key generation."
fi

exit 0