<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Events\ShiftCancelled;
use App\Jobs\SendShiftNoShowTelegramNotificationJob;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftEvent;
use App\Models\ShiftPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Soft-cancel shifts, log {@see ShiftEvent}, and dispatch {@see ShiftCancelled}
 * so Telegram debounce / replacement checks match driver and staff flows.
 * Also hard-removes started no-show shifts and notifies Telegram with a snapshot.
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

    /**
     * Staff permanently removes a started booked shift (no-show): free the vehicle for
     * normal free-slot math, log audit context, and notify Telegram with a snapshot
     * (the row is deleted, so cancellation debounce / replacement checks do not apply).
     */
    public function removeNoShowByStaff(Shift $shift, User $staff): void
    {
        $shift->loadMissing(['station', 'vehicle', 'originalVehicle', 'driver']);

        $meta = [
            'shift_id' => $shift->id,
            'station_id' => $shift->station_id,
            'vehicle_id' => $shift->vehicle_id,
            'driver_id' => $shift->driver_id,
            'starts_at' => $shift->starts_at?->toIso8601String(),
            'ends_at' => $shift->ends_at?->toIso8601String(),
            'admin_user_id' => $staff->id,
        ];

        $payload = $this->noShowTelegramPayload($shift, $staff);
        $shift->delete();
        Log::info('shift.admin_removed_no_show', $meta);
        SendShiftNoShowTelegramNotificationJob::dispatch($payload);
    }

    /**
     * @return array{
     *     shift_id: int,
     *     station_name: string,
     *     station_address: string,
     *     vehicle_line: string,
     *     slot_line: string,
     *     driver_line: string,
     *     staff_line: string
     * }
     */
    private function noShowTelegramPayload(Shift $shift, User $staff): array
    {
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone');
        $starts = $shift->starts_at->copy()->setTimezone($tz);
        $ends = $shift->ends_at->copy()->setTimezone($tz);

        $vehicle = $shift->originalVehicle ?? $shift->vehicle;
        $vehicleLine = '';
        if ($vehicle instanceof FleetVehicle) {
            $plate = trim((string) $vehicle->registration_number);
            $label = trim((string) $vehicle->label);
            if ($plate !== '') {
                $vehicleLine = 'Vehicle: '.$plate;
            } elseif ($label !== '') {
                $vehicleLine = 'Vehicle: '.$label;
            }
        }

        $driverLine = '';
        if ($shift->driver) {
            $driverLine = 'Driver: '.$shift->driver->name;
        }

        return [
            'shift_id' => (int) $shift->id,
            'station_name' => $shift->station?->name ?? '—',
            'station_address' => $shift->station?->address ?? '',
            'vehicle_line' => $vehicleLine,
            'slot_line' => 'Slot freed: '.$starts->format('Y-m-d H:i').'-'.$ends->format('H:i'),
            'driver_line' => $driverLine,
            'staff_line' => 'Removed by: '.$staff->name.' (staff, no-show)',
        ];
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
