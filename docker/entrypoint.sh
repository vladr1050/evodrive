#!/bin/sh
set -e
# Copy built public assets to shared volume so nginx can serve them (one-time).
if [ -d /var/www/html/public/build ] && [ -n "$(ls -A /var/www/html/public/build 2>/dev/null)" ]; then
  cp -rn /var/www/html/public/. /shared/public/ 2>/dev/null || true
  chown -R www-data:www-data /shared/public 2>/dev/null || true
fi
exec "$@"
