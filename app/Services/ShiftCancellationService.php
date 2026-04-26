<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Events\ShiftCancelled;
use App\Models\Driver;
use App\Models\Shift;
use App\Models\ShiftEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Soft-cancel shifts, log {@see ShiftEvent}, and dispatch {@see ShiftCancelled}
 * so Telegram debounce / replacement checks match driver and staff flows.
 */
class ShiftCancellationService
{
    /**
     * Driver cancelled their own shift (portal).
     */
    public function cancelByDriver(Shift $shift, Driver $driver, ?string $reason = null): void
    {
        $shift->update([
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => $driver->id,
            'cancelled_by_user_id' => null,
            'cancel_reason' => $reason ?? 'cancelled_by_driver',
        ]);

        $fresh = $shift->fresh();
        ShiftEvent::logCancelled($fresh, 'driver', (int) $driver->id);
        $this->dispatchCancelled($fresh);
    }

    /**
     * Staff cancelled from Filament (same downstream behaviour as driver cancel).
     */
    public function cancelByStaff(Shift $shift, User $staff, ?string $reason = null): void
    {
        $shift->update([
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => null,
            'cancelled_by_user_id' => $staff->id,
            'cancel_reason' => $reason ?? 'cancelled_by_staff',
        ]);

        $fresh = $shift->fresh();
        ShiftEvent::logCancelled($fresh, 'admin', (int) $staff->id);
        $this->dispatchCancelled($fresh);
    }

    private function dispatchCancelled(Shift $shift): void
    {
        $shift = $shift->fresh(['driver']);
        $driver = $shift->driver;
        if ($driver) {
            Event::dispatch(new ShiftCancelled($shift, $driver));
        }
    }
}
