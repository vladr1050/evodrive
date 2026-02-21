#!/bin/sh
set -e
# Create storage symlink in app container (Laravel public_path -> storage/app/public).
php artisan storage:link 2>/dev/null || true
# Copy built public assets to shared volume so nginx can serve them (one-time).
if [ -d /var/www/html/public/build ] && [ -n "$(ls -A /var/www/html/public/build 2>/dev/null)" ]; then
  cp -rn /var/www/html/public/. /shared/public/ 2>/dev/null || true
  chown -R www-data:www-data /shared/public 2>/dev/null || true
fi
# Ensure storage symlink exists in shared public so nginx can serve uploads (logo, favicon).
# ln -sf so it works after every deploy; target must be path nginx resolves (it has storage_app at /var/www/html/storage).
mkdir -p /shared/public
rm -f /shared/public/storage
ln -sf /var/www/html/storage/app/public /shared/public/storage
chown -h www-data:www-data /shared/public/storage 2>/dev/null || true
exec "$@"
