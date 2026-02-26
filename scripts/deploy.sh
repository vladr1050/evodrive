#!/usr/bin/env bash
# Safe, non-destructive production deploy for EvoDrive.
# Uses git pull --ff-only. Rolls back on failure. Requires clean working tree.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOCK_FILE="$PROJECT_ROOT/.deploy.lock"
REMOTE="origin"
BRANCH="main"
OLD_COMMIT=""

cleanup() {
  if [ -f "$LOCK_FILE" ]; then
    rm -f "$LOCK_FILE"
  fi
}

rollback() {
  if [ -n "$OLD_COMMIT" ] && [ -d "$PROJECT_ROOT/.git" ]; then
    echo ""
    echo "ROLLBACK TO $OLD_COMMIT"
    cd "$PROJECT_ROOT" && git reset --hard "$OLD_COMMIT"
  fi
  exit 1
}

trap cleanup EXIT
trap rollback ERR

cd "$PROJECT_ROOT"

# 1) Locking
if [ -f "$LOCK_FILE" ]; then
  echo "Deploy already running (lock file exists). Aborting."
  exit 1
fi
touch "$LOCK_FILE"

# 2) Safety - working tree must be clean
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "FAIL: Working tree is dirty. Commit or stash changes before deploying."
  exit 1
fi

# Fetch
git fetch "$REMOTE"

# Show what would change
CURRENT=$(git rev-parse HEAD)
NEW_COMMITS=$(git log HEAD.."$REMOTE/$BRANCH" --oneline 2>/dev/null || true)
FILES_CHANGED=$(git diff --name-only HEAD.."$REMOTE/$BRANCH" 2>/dev/null || true)

echo "=== Deploy Preview ==="
echo "CURRENT COMMIT: $CURRENT"
echo ""
echo "NEW COMMITS:"
if [ -z "$NEW_COMMITS" ]; then
  echo "  (none)"
else
  echo "$NEW_COMMITS" | sed 's/^/  /'
fi
echo ""
echo "FILES CHANGED:"
if [ -z "$FILES_CHANGED" ]; then
  echo "  (none)"
else
  echo "$FILES_CHANGED" | sed 's/^/  /'
fi
echo ""

# If no changes, exit cleanly
if [ -z "$NEW_COMMITS" ] && [ -z "$FILES_CHANGED" ]; then
  echo "No changes to deploy. Exiting."
  exit 0
fi

# Save old commit for rollback
OLD_COMMIT=$(git rev-parse HEAD)

# 3) Deployment
echo "--- Pulling ---"
git pull --ff-only "$REMOTE" "$BRANCH"

NEW_COMMIT=$(git rev-parse HEAD)

# Detect what changed
COMPOSER_CHANGED=0
FRONTEND_CHANGED=0
MIGRATIONS_CHANGED=0

CHANGED_FILES=$(git diff --name-only "$OLD_COMMIT" "$NEW_COMMIT" 2>/dev/null || true)
for f in $CHANGED_FILES; do
  [[ "$f" == "composer.json" || "$f" == "composer.lock" ]] && COMPOSER_CHANGED=1
  [[ "$f" == "package.json" || "$f" == "package-lock.json" ]] && FRONTEND_CHANGED=1
  [[ "$f" == vite.config* ]] && FRONTEND_CHANGED=1
  [[ "$f" == resources/* ]] && FRONTEND_CHANGED=1
  [[ "$f" == tailwind.config* ]] && FRONTEND_CHANGED=1
  [[ "$f" == database/migrations/* ]] && MIGRATIONS_CHANGED=1
done

# Run composer if needed
if [ $COMPOSER_CHANGED -eq 1 ]; then
  echo "--- Composer install ---"
  composer install --no-dev --prefer-dist --no-interaction
fi

# Run npm if needed
if [ $FRONTEND_CHANGED -eq 1 ]; then
  echo "--- npm ci && build ---"
  npm ci
  npm run build
fi

# Run migrations if needed
if [ $MIGRATIONS_CHANGED -eq 1 ]; then
  echo "--- Migrate ---"
  php artisan migrate --force
fi

# Always run cache commands
echo "--- Cache optimization ---"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Success - clear rollback trap (we're committed)
trap - ERR

echo ""
echo "DEPLOY SUCCESS"
echo "NEW COMMIT: $NEW_COMMIT"
exit 0
