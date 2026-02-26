<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftAvailabilityService
{
    public function checkAvailability(int $stationId, Carbon $startsAt, float $durationHours): array
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            return ['count' => 0, 'vehicle_ids' => []];
        }

        $this->validatePolicy($policy, $startsAt, $durationHours);

        $endsAt = $startsAt->copy()->addMinutes((int) round($durationHours * 60));

        $vehicles = FleetVehicle::where('home_station_id', $stationId)
            ->where('status', VehicleStatus::Active)
            ->get();

        if ($vehicles->isEmpty()) {
            return ['count' => 0, 'vehicle_ids' => []];
        }

        $vehicleIds = $vehicles->pluck('id')->all();
        $shiftsByVehicle = $this->fetchRelevantShiftsForVehicles($vehicleIds, $startsAt, $endsAt, $policy);

        $availableIds = [];
        foreach ($vehicles as $vehicle) {
            $shifts = $shiftsByVehicle->get($vehicle->id, collect());
            if ($this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy)) {
                $availableIds[] = $vehicle->id;
            }
        }

        return [
            'count' => count($availableIds),
            'vehicle_ids' => $availableIds,
        ];
    }

    /**
     * Prefetch all shifts that could affect availability for the given slot (overlap, adjacent for downtime, same day for 24h cap).
     * Returns a collection keyed by vehicle_id of Shift collections.
     */
    protected function fetchRelevantShiftsForVehicles(array $vehicleIds, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy): Collection
    {
        $tz = $policy->timezone ?? 'UTC';
        $dayStart = $startsAt->copy()->setTimezone($tz)->startOfDay();
        $dayEnd = $startsAt->copy()->setTimezone($tz)->endOfDay();
        $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
        $dayEndUtc = $dayEnd->copy()->setTimezone('UTC');
        $windowStart = $startsAt->copy()->subHours(24)->min($dayStartUtc->copy()->subHours(24));
        $windowEnd = $endsAt->copy()->addHours(24)->max($dayEndUtc->copy()->addHours(24));

        $shifts = Shift::whereIn('vehicle_id', $vehicleIds)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '<', $windowEnd)
            ->where('ends_at', '>', $windowStart)
            ->orderBy('vehicle_id')
            ->orderBy('starts_at')
            ->get();

        return $shifts->groupBy('vehicle_id');
    }

    /**
     * Check without throwing (for internal use after policy validation).
     * Used by ShiftBookingService for a single vehicle under lock; loads shifts in one query then evaluates in memory.
     */
    public function vehicleAvailableFor(int $vehicleId, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy): bool
    {
        $shiftsByVehicle = $this->fetchRelevantShiftsForVehicles([$vehicleId], $startsAt, $endsAt, $policy);
        $shifts = $shiftsByVehicle->get($vehicleId, collect());

        return $this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy);
    }

    /**
     * Evaluate availability for a slot given a collection of relevant shifts (no DB queries).
     */
    protected function vehicleAvailableForWithShifts(Collection $shifts, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy): bool
    {
        $booked = $shifts->filter(fn (Shift $s) => $s->status === ShiftStatus::Booked);

        $overlapping = $booked->contains(fn (Shift $s) => $s->starts_at->lt($endsAt) && $s->ends_at->gt($startsAt));
        if ($overlapping) {
            return false;
        }

        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);

        $prev = $booked->filter(fn (Shift $s) => $s->ends_at->lte($startsAt))->sortByDesc('ends_at')->first();
        if ($prev !== null) {
            $prevGapMinutes = (int) $prev->ends_at->diffInMinutes($startsAt, false);
            if ($prevGapMinutes <= 0) {
                return false;
            }
            if ($prevGapMinutes < $downtimeMinutes) {
                return false;
            }
        }

        $next = $booked->filter(fn (Shift $s) => $s->starts_at->gte($endsAt))->sortBy('starts_at')->first();
        if ($next !== null) {
            $nextGapMinutes = (int) $endsAt->diffInMinutes($next->starts_at, false);
            if ($nextGapMinutes <= 0) {
                return false;
            }
            if ($nextGapMinutes < $downtimeMinutes) {
                return false;
            }
        }

        $tz = $policy->timezone ?? 'UTC';
        $startsAtInTz = $startsAt->copy()->setTimezone($tz);
        $dayStart = $startsAtInTz->copy()->startOfDay();
        $dayEnd = $startsAtInTz->copy()->endOfDay();
        $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
        $dayEndUtc = $dayEnd->copy()->setTimezone('UTC');

        $shiftsOverlappingDay = $booked->filter(fn (Shift $s) => $s->starts_at->lt($dayEndUtc) && $s->ends_at->gt($dayStartUtc));

        $totalMinutesSameDay = $shiftsOverlappingDay->sum(function (Shift $s) use ($dayStart, $dayEnd, $tz) {
            $sStart = $s->starts_at->copy()->setTimezone($tz);
            $sEnd = $s->ends_at->copy()->setTimezone($tz);
            $from = $sStart->isBefore($dayStart) ? $dayStart->copy() : $sStart;
            $to = $sEnd->isAfter($dayEnd) ? $dayEnd->copy() : $sEnd;
            if ($from->gte($to)) {
                return 0;
            }
            return $from->diffInMinutes($to);
        });

        $newStartInTz = $startsAt->copy()->setTimezone($tz);
        $newEndInTz = $endsAt->copy()->setTimezone($tz);
        $newFrom = $newStartInTz->isBefore($dayStart) ? $dayStart->copy() : $newStartInTz;
        $newTo = $newEndInTz->isAfter($dayEnd) ? $dayEnd->copy() : $newEndInTz;
        $newMinutes = $newFrom->gte($newTo) ? 0 : $newFrom->diffInMinutes($newTo);

        if ($totalMinutesSameDay + $newMinutes > 24 * 60) {
            return false;
        }

        return true;
    }

    /**
     * @throws ShiftBookingException
     */
    protected function validatePolicy(ShiftPolicy $policy, Carbon $startsAt, float $durationHours): void
    {
        $slotMinutes = $policy->time_slot_minutes ?? 15;
        $minute = $startsAt->hour * 60 + $startsAt->minute;
        if ($minute % $slotMinutes !== 0) {
            throw ShiftBookingException::invalidStartTime();
        }

        $allowed = $policy->allowedDurations();
        if (! in_array((int) round($durationHours), $allowed, true)) {
            throw ShiftBookingException::invalidDuration();
        }

        $minDuration = $policy->min_duration_hours ?? 4;
        if ($durationHours < $minDuration) {
            throw ShiftBookingException::invalidDuration();
        }
    }
}
