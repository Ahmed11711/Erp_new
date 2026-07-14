#!/usr/bin/env bash
# ============================================================
#  Laravel production optimization — run on the server after deploy
#  Usage: cd backend && bash scripts/optimize-for-production.sh
# ============================================================
set -euo pipefail

cd "$(dirname "$0")/.."

echo "[1/6] Composer autoload optimization..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[2/6] Clearing old caches..."
php artisan optimize:clear

echo "[3/6] Running migrations..."
php artisan migrate --force --no-interaction

echo "[4/6] Caching config, routes, views, events..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[5/6] Final optimize..."
php artisan optimize

echo "[6/6] Done."
echo ""
echo "IMPORTANT: Ensure .env on server has:"
echo "  APP_ENV=production"
echo "  APP_DEBUG=false"
echo "  LOG_LEVEL=error"
echo "  CACHE_DRIVER=file  (or redis if available)"
echo "  QUEUE_CONNECTION=database  (not sync)"
echo ""
echo "For frontend: cd front-end && npm ci && ng build --configuration production"
