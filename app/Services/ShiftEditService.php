<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\Shift;
use App\Models\ShiftEvent;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Driver shift edit: future shifts (move start + duration) or ongoing shifts (extend end only).
 * Respects policy: allowed durations, min duration, time slot, vehicle downtime.
 */
class ShiftEditService
{
    public function __construct(
        protected ShiftAvailabilityService $availabilityService
    ) {}

    /**
     * Whether the driver can edit this shift (without cancelling).
     * True only if there is at least policy's downtime between this shift and the previous/next
     * booked shift on the same vehicle (or no previous/next).
     */
    public function canEditShift(Shift $shift): bool
    {
        if ($shift->status !== ShiftStatus::Booked) {
            return false;
        }

        $policy = ShiftPolicy::active();
        if (! $policy) {
            return false;
        }

        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);
        $otherOnVehicle = Shift::where('vehicle_id', $shift->vehicle_id)
            ->where('id', '!=', $shift->id)
            ->where('status', ShiftStatus::Booked)
            ->orderBy('starts_at')
            ->get();

        $prev = $otherOnVehicle->filter(fn (Shift $s) => $s->ends_at->lte($shift->starts_at))->sortByDesc('ends_at')->first();
        if ($prev !== null) {
            $gap = (int) $prev->ends_at->diffInMinutes($shift->starts_at, false);
            if ($gap < $downtimeMinutes) {
                return false;
            }
        }

        $next = $otherOnVehicle->filter(fn (Shift $s) => $s->starts_at->gte($shift->ends_at))->sortBy('starts_at')->first();
        if ($next !== null) {
            $gap = (int) $shift->ends_at->diffInMinutes($next->starts_at, false);
            if ($gap < $downtimeMinutes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Next booked shift on the same vehicle that starts at or after this shift's current end (UTC).
     * Used for extend hints and max end time.
     */
    public function nextBookedShiftOnVehicleAfter(Shift $shift): ?Shift
    {
        return Shift::query()
            ->where('vehicle_id', $shift->vehicle_id)
            ->where('id', '!=', $shift->id)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '>=', $shift->ends_at)
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Allowed total duration hours (from shift start) the driver can extend to; each value is in policy allowed list, strictly longer than current, and passes vehicle rules.
     *
     * @return list<int>
     */
    public function allowedExtensionDurationsHours(Shift $shift, Carbon $nowInPolicyTz): array
    {
        if ($shift->status !== ShiftStatus::Booked) {
            return [];
        }

        $policy = ShiftPolicy::active();
        if (! $policy) {
            return [];
        }

        $tz = $policy->timezone ?? 'UTC';
        $startTz = $shift->starts_at->copy()->setTimezone($tz);
        $endTz = $shift->ends_at->copy()->setTimezone($tz);
        if (! $startTz->lte($nowInPolicyTz) || ! $endTz->gt($nowInPolicyTz)) {
            return [];
        }

        $currentDur = (int) round($shift->durationHours());
        $next = $this->nextBookedShiftOnVehicleAfter($shift);
        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);
        $maxEndUtc = null;
        if ($next !== null) {
            $maxEndUtc = $next->starts_at->copy()->subMinutes($downtimeMinutes);
        }

        $out = [];
        foreach ($policy->allowedDurations() as $d) {
            $d = (int) $d;
            if ($d <= $currentDur) {
                continue;
            }
            $newEndsUtc = $shift->starts_at->copy()->addHours($d);
            if ($newEndsUtc->lte($shift->ends_at)) {
                continue;
            }
            if ($maxEndUtc !== null && $newEndsUtc->gt($maxEndUtc)) {
                continue;
            }
            if (! $this->availabilityService->vehicleAvailableForExcludingShift(
                (int) $shift->vehicle_id,
                (int) $shift->id,
                $shift->starts_at->copy(),
                $newEndsUtc->copy(),
                $policy
            )) {
                continue;
            }
            $out[] = $d;
        }

        return $out;
    }

    /**
     * Extend an in-progress booked shift to a longer allowed total duration (same start, new end).
     *
     * @throws ShiftBookingException
     */
    public function extendOngoingShift(Shift $shift, int $newDurationHoursInt, Carbon $nowInPolicyTz): Shift
    {
        if ($shift->status !== ShiftStatus::Booked) {
            throw ShiftBookingException::shiftNotEditable();
        }

        $policy = ShiftPolicy::active();
        if (! $policy) {
            throw ShiftBookingException::noVehiclesAvailable();
        }

        $allowed = $this->allowedExtensionDurationsHours($shift, $nowInPolicyTz);
        if (! in_array($newDurationHoursInt, $allowed, true)) {
            throw ShiftBookingException::invalidDuration();
        }

        $newEndsUtc = $shift->starts_at->copy()->addHours($newDurationHoursInt);

        if (Shift::driverHasOverlappingBookedShift((int) $shift->driver_id, $shift->starts_at->copy()->utc(), $newEndsUtc->copy()->utc(), (int) $shift->id)) {
            throw ShiftBookingException::driverShiftOverlap();
        }

        return DB::transaction(function () use ($shift, $newEndsUtc) {
            $shift->update([
                'ends_at' => $newEndsUtc,
            ]);

            ShiftEvent::logEdited($shift->fresh(), 'driver', (int) $shift->driver_id);

            return $shift->fresh(['vehicle', 'station']);
        });
    }

    /**
     * Validate and update shift to new start time and duration. Keeps vehicle and station.
     *
     * @throws ShiftBookingException
     */
    public function updateShift(Shift $shift, Carbon $newStartsAt, float $newDurationHours): Shift
    {
        if ($shift->status !== ShiftStatus::Booked) {
            throw ShiftBookingException::shiftNotEditable();
        }

        $policy = ShiftPolicy::active();
        if (! $policy) {
            throw ShiftBookingException::noVehiclesAvailable();
        }

        if (! $this->canEditShift($shift)) {
            throw ShiftBookingException::shiftNotEditable();
        }

        $this->validatePolicy($policy, $newStartsAt, $newDurationHours);
        $this->validateDriverDailyLimit($shift->driver_id, $newStartsAt, $policy, $shift->id);

        $newEndsAt = $newStartsAt->copy()->addMinutes((int) round($newDurationHours * 60));
        $newStartsAtUtc = $newStartsAt->copy()->utc();
        $newEndsAtUtc = $newEndsAt->copy()->utc();

        if (Shift::driverHasOverlappingBookedShift((int) $shift->driver_id, $newStartsAtUtc, $newEndsAtUtc, (int) $shift->id)) {
            throw ShiftBookingException::driverShiftOverlap();
        }

        $vehicleId = (int) $shift->vehicle_id;
        if (! $this->availabilityService->vehicleAvailableForExcludingShift($vehicleId, (int) $shift->id, $newStartsAtUtc, $newEndsAtUtc, $policy)) {
            throw ShiftBookingException::noVehiclesAvailable();
        }

        return DB::transaction(function () use ($shift, $newStartsAtUtc, $newEndsAtUtc) {
            $shift->update([
                'starts_at' => $newStartsAtUtc,
                'ends_at' => $newEndsAtUtc,
            ]);

            ShiftEvent::logEdited($shift->fresh(), 'driver', (int) $shift->driver_id);

            return $shift->fresh(['vehicle', 'station']);
        });
    }

    protected function validatePolicy(ShiftPolicy $policy, Carbon $startsAt, float $durationHours): void
    {
        $slotMinutes = $policy->time_slot_minutes ?? 15;
        $minute = $startsAt->hour * 60 + $startsAt->minute;
        if ($slotMinutes > 0 && $minute % $slotMinutes !== 0) {
            throw ShiftBookingException::invalidStartTime();
        }

        $allowed = $policy->allowedDurations();
        $durationInt = (int) round($durationHours);
        if (! in_array($durationInt, $allowed, true)) {
            throw ShiftBookingException::invalidDuration();
        }

        $minDuration = $policy->min_duration_hours ?? 4;
        if ($durationHours < $minDuration) {
            throw ShiftBookingException::invalidDuration();
        }
    }

    /**
     * Same as booking: driver daily limit, but excluding the shift we're editing.
     */
    protected function validateDriverDailyLimit(int $driverId, Carbon $startsAt, ShiftPolicy $policy, int $excludeShiftId): void
    {
        $max = $policy->max_shifts_per_driver_per_day;
        if ($max === null) {
            return;
        }
        $dayStart = $startsAt->copy()->startOfDay();
        $dayEnd = $startsAt->copy()->endOfDay();
        $count = Shift::where('driver_id', $driverId)
            ->where('id', '!=', $excludeShiftId)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<=', $dayEnd)
            ->count();
        if ($count >= $max) {
            throw ShiftBookingException::dailyLimitExceeded();
        }
    }
}
