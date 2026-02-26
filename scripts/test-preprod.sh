#!/usr/bin/env bash
# EvoDrive pre-production test script
# Clears caches, installs deps, runs PHPUnit with JUnit output.
# Usage: run from project root (evodrive-app)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
REPORT_DIR="$PROJECT_ROOT/storage/test-reports"

cd "$PROJECT_ROOT"

echo "=== EvoDrive Pre-Production Checks ==="
echo "Project root: $PROJECT_ROOT"
echo ""

# 1. Clear caches
echo "--- Clearing caches ---"
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
echo "Caches cleared."
echo ""

# 2. Install composer dependencies
echo "--- Installing Composer dependencies ---"
composer install --no-interaction --prefer-dist --optimize-autoloader
echo ""

# 3. Create report directory
mkdir -p "$REPORT_DIR"
JUNIT_FILE="$REPORT_DIR/junit.xml"

# 4. Run PHPUnit tests (JUnit output via phpunit.xml)
echo "--- Running PHPUnit tests ---"
php artisan test --testsuite=Feature \
  --filter="ShiftBookingTest|ShiftCopyWeekTest|ShiftVehicle24hCapTest|ShiftTimezoneWeekBoundaryTest|ShiftAvailabilityPerformanceTest|DriverPortalProfileTest|DriverPortalShiftCancelTest|CompleteShiftsCommandTest"

echo ""
if [ -f "$JUNIT_FILE" ]; then
  echo "JUnit report written to: $JUNIT_FILE"
else
  echo "Note: JUnit report may be at storage/test-reports/junit.xml"
fi
echo "=== Pre-production test phase complete ==="
