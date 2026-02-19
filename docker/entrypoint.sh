#!/bin/sh
set -e
# Create storage symlink in app container (Laravel public_path -> storage/app/public).
php artisan storage:link 2>/dev/null || true
# Copy built public assets to shared volume so nginx can serve them (one-time).
if [ -d /var/www/html/public/build ] && [ -n "$(ls -A /var/www/html/public/build 2>/dev/null)" ]; then
  cp -rn /var/www/html/public/. /shared/public/ 2>/dev/null || true
  chown -R www-data:www-data /shared/public 2>/dev/null || true
fi
# Ensure storage symlink exists in shared public (nginx serves from this volume; needs symlink + storage_app mount).
if [ -L /var/www/html/public/storage ]; then
  rm -f /shared/public/storage 2>/dev/null || true
  cp -a /var/www/html/public/storage /shared/public/ 2>/dev/null || true
  chown -h www-data:www-data /shared/public/storage 2>/dev/null || true
fi
exec "$@"
