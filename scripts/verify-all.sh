#!/usr/bin/env bash
# EvoDrive full verification: PHPUnit, npm build, E2E tests.
# Writes preflight_report.md in repo root. Exits non-zero on any failure.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
REPORT_DIR="$PROJECT_ROOT/storage/test-reports"
REPORT_FILE="$PROJECT_ROOT/preflight_report.md"
TMP_DIR=$(mktemp -d)
trap "rm -rf '$TMP_DIR'" EXIT

cd "$PROJECT_ROOT"
mkdir -p "$REPORT_DIR"

PHP_LOG="$TMP_DIR/phpunit.log"
NPM_LOG="$TMP_DIR/npm.log"
E2E_LOG="$TMP_DIR/e2e.log"

PHPUNIT_STATUS="FAIL"
NPM_STATUS="FAIL"
E2E_STATUS="FAIL"

echo "=== EvoDrive Full Verification ==="
echo ""

# 1. PHPUnit
echo "--- 1. PHPUnit Feature Tests ---"
if php artisan test --testsuite=Feature > "$PHP_LOG" 2>&1; then
  PHPUNIT_STATUS="PASS"
  echo "[PASS] PHPUnit"
  cat "$PHP_LOG"
else
  echo "[FAIL] PHPUnit"
  tail -80 "$PHP_LOG"
fi
echo ""

# 2. npm ci + build
echo "--- 2. npm ci && npm run build ---"
if (npm ci > "$NPM_LOG" 2>&1) && (npm run build >> "$NPM_LOG" 2>&1); then
  NPM_STATUS="PASS"
  echo "[PASS] npm build"
  tail -20 "$NPM_LOG"
else
  echo "[FAIL] npm build"
  tail -80 "$NPM_LOG"
fi
echo ""

# 3. E2E
echo "--- 3. Playwright E2E Tests ---"
if npm run e2e > "$E2E_LOG" 2>&1; then
  E2E_STATUS="PASS"
  echo "[PASS] E2E"
  tail -25 "$E2E_LOG"
else
  echo "[FAIL] E2E"
  tail -80 "$E2E_LOG"
fi
echo ""

# Gather metadata
GIT_HASH=$(git rev-parse HEAD 2>/dev/null || echo "n/a")
PHP_VER=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "n/a")
NODE_VER=$(node -v 2>/dev/null || echo "n/a")
NPM_VER=$(npm -v 2>/dev/null || echo "n/a")

# Env summary (non-sensitive)
ENV_SUMMARY="APP_ENV=${APP_ENV:-not set}, DB_CONNECTION=${DB_CONNECTION:-from config}, NODE_ENV=${NODE_ENV:-not set}"

# Determine WARN: PHPUnit has 1 skipped test
PHPUNIT_WARN=""
if grep -q "1 skipped" "$PHP_LOG" 2>/dev/null; then
  PHPUNIT_WARN=" (1 skipped)"
fi

# Write markdown report
{
  echo "# EvoDrive Preflight Report"
  echo ""
  echo "Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  echo ""
  echo "## Environment"
  echo ""
  echo "| Item | Value |"
  echo "|------|-------|"
  echo "| Git commit | \`${GIT_HASH}\` |"
  echo "| PHP | $PHP_VER |"
  echo "| Node | $NODE_VER |"
  echo "| npm | $NPM_VER |"
  echo "| Env summary | $ENV_SUMMARY |"
  echo ""
  echo "## Results"
  echo ""
  echo "| Step | Status |"
  echo "|------|--------|"
  echo "| PHPUnit Feature Tests | **$PHPUNIT_STATUS**$PHPUNIT_WARN |"
  echo "| npm ci + build | **$NPM_STATUS** |"
  echo "| Playwright E2E | **$E2E_STATUS** |"
  echo ""
  echo "## Reports"
  echo ""
  echo "- **PHPUnit JUnit:** \`storage/test-reports/junit.xml\`"
  echo "- **Playwright HTML:** \`storage/test-reports/playwright-report/index.html\`"
  echo ""

  # Append failure logs if any step failed
  if [ "$PHPUNIT_STATUS" = "FAIL" ]; then
    echo "---"
    echo ""
    echo "## PHPUnit Failure Log (last 80 lines)"
    echo ""
    echo '```'
    tail -80 "$PHP_LOG"
    echo '```'
    echo ""
  fi
  if [ "$NPM_STATUS" = "FAIL" ]; then
    echo "---"
    echo ""
    echo "## npm build Failure Log (last 80 lines)"
    echo ""
    echo '```'
    tail -80 "$NPM_LOG"
    echo '```'
    echo ""
  fi
  if [ "$E2E_STATUS" = "FAIL" ]; then
    echo "---"
    echo ""
    echo "## E2E Failure Log (last 80 lines)"
    echo ""
    echo '```'
    tail -80 "$E2E_LOG"
    echo '```'
    echo ""
  fi
} > "$REPORT_FILE"

# Summary
echo "=== Summary ==="
if [ "$PHPUNIT_STATUS" = "PASS" ] && [ "$NPM_STATUS" = "PASS" ] && [ "$E2E_STATUS" = "PASS" ]; then
  echo "ALL CHECKS PASSED"
  echo "Report: preflight_report.md"
  echo "  - PHPUnit JUnit: storage/test-reports/junit.xml"
  echo "  - Playwright HTML: storage/test-reports/playwright-report/index.html"
  exit 0
else
  echo "SOME CHECKS FAILED"
  echo "Report: $REPORT_FILE"
  exit 1
fi
