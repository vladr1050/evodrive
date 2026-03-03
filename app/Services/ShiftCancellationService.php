<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Events\ShiftCancelled;
use App\Jobs\SendShiftCancellationTelegramNotificationJob;
use App\Models\Driver;
use App\Models\Shift;
use Illuminate\Support\Facades\Event;

/**
 * Handles driver shift cancellation: soft-cancel (status + metadata) and
 * schedules delayed Telegram notification (debounced, anti-spam).
 */
class ShiftCancellationService
{
    /**
     * Cancel a shift for the given driver (no delete). Fires ShiftCancelled
     * so a listener can schedule the debounced Telegram job.
     */
    public function cancelShift(Shift $shift, Driver $driver, ?string $reason = null): void
    {
        $shift->update([
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => $driver->id,
            'cancel_reason' => $reason ?? 'cancelled_by_driver',
        ]);

        Event::dispatch(new ShiftCancelled($shift->fresh(), $driver));
    }
}
