#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "→ git pull"
git pull

echo "→ composer install"
composer install --no-dev --optimize-autoloader

echo "→ npm ci && npm run build"
npm ci
npm run build

echo "→ migrations"
php artisan migrate --force

echo "→ storage link (public/storage → storage/app/public)"
php artisan storage:link 2>/dev/null || true

echo "→ caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ permissions"
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "→ restart queue (if systemd service exists)"
systemctl restart evodrive-queue 2>/dev/null || true

echo "Done."
