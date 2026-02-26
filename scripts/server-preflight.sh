#!/usr/bin/env bash
# Server preflight check for EvoDrive production deployment.
# Exits non-zero if any critical check fails.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$PROJECT_ROOT"

FAILED=0

echo "=== EvoDrive Server Preflight ==="
echo ""

# Environment info
echo "--- Environment ---"
echo "OS: $(uname -s) $(uname -r)"
if command -v php >/dev/null 2>&1; then
  echo "PHP: $(php -r 'echo PHP_VERSION;')"
else
  echo "PHP: MISSING"
  FAILED=1
fi
if command -v composer >/dev/null 2>&1; then
  echo "Composer: $(composer --version 2>/dev/null | head -1)"
else
  echo "Composer: not found"
fi
if command -v node >/dev/null 2>&1; then
  echo "Node: $(node -v)"
  echo "npm: $(npm -v 2>/dev/null || echo 'n/a')"
else
  echo "Node: not found"
fi
if [ -f artisan ] && command -v php >/dev/null 2>&1; then
  echo "Laravel: $(php artisan --version 2>/dev/null || echo 'n/a')"
fi
echo ""
echo "--- Laravel Config ---"
if [ -f .env ]; then
  echo "APP_ENV: $(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- || echo 'not set')"
  echo "APP_DEBUG: $(grep -E '^APP_DEBUG=' .env 2>/dev/null | cut -d= -f2- || echo 'not set')"
  echo "APP_URL: $(grep -E '^APP_URL=' .env 2>/dev/null | cut -d= -f2- || echo 'not set')"
  echo "DB_CONNECTION: $(grep -E '^DB_CONNECTION=' .env 2>/dev/null | cut -d= -f2- || echo 'not set')"
else
  echo ".env not found"
fi
echo ""

# Critical checks
echo "--- Critical Checks ---"

if ! command -v php >/dev/null 2>&1; then
  echo "FAIL: PHP is required"
  exit 1
fi

if [ ! -w storage ] 2>/dev/null; then
  echo "FAIL: storage is not writable"
  FAILED=1
else
  echo "OK: storage writable"
fi

if [ ! -w bootstrap/cache ] 2>/dev/null; then
  echo "FAIL: bootstrap/cache is not writable"
  FAILED=1
else
  echo "OK: bootstrap/cache writable"
fi

if [ ! -e public/storage ] || [ ! -L public/storage ]; then
  echo "WARN: public/storage symlink missing (run: php artisan storage:link)"
else
  echo "OK: public/storage symlink exists"
fi

if php artisan migrate:status >/dev/null 2>&1; then
  echo "OK: Database connection works"
else
  echo "FAIL: Database not reachable (php artisan migrate:status failed)"
  FAILED=1
fi

echo ""
if [ $FAILED -eq 0 ]; then
  echo "PRECHECK: OK"
  exit 0
else
  echo "PRECHECK: FAILED"
  exit 1
fi
