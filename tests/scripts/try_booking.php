<?php

/**
 * CLI script for concurrent booking tests. Run from project root.
 * Usage: php tests/scripts/try_booking.php <driver_id> <station_id> <starts_at_iso> <duration_hours>
 * Expects env: DB_DATABASE (and usual Laravel DB env). Outputs SUCCESS or FAIL:<reasonCode>
 */

$appRoot = dirname(__DIR__, 2);
chdir($appRoot);

require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$driverId = (int) ($argv[1] ?? 0);
$stationId = (int) ($argv[2] ?? 0);
$startsAtIso = $argv[3] ?? '';
$durationHours = (float) ($argv[4] ?? 0);

if (! $driverId || ! $stationId || ! $startsAtIso || $durationHours <= 0) {
    echo 'FAIL:INVALID_ARGS';
    exit(1);
}

$startsAt = Carbon\Carbon::parse($startsAtIso);

try {
    $service = app(\App\Services\ShiftBookingService::class);
    $service->bookShift($driverId, $stationId, $startsAt, $durationHours);
    echo 'SUCCESS';
} catch (\App\Exceptions\ShiftBookingException $e) {
    echo 'FAIL:' . $e->reasonCode;
    exit(1);
}
