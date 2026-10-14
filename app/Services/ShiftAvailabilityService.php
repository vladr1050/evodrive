<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleServiceBlock;
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
        $serviceBlockedSet = $this->fetchServiceBlockedVehicleIdSet($vehicleIds, $startsAt, $endsAt);

        $availableIds = [];
        foreach ($vehicles as $vehicle) {
            $shifts = $shiftsByVehicle->get($vehicle->id, collect());
            if ($this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy, $vehicle->id, $serviceBlockedSet)) {
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

        return $this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy, $vehicleId, null);
    }

    /**
     * Like vehicleAvailableFor but excludes one shift (e.g. the one being edited).
     */
    public function vehicleAvailableForExcludingShift(int $vehicleId, int $excludeShiftId, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy): bool
    {
        $shiftsByVehicle = $this->fetchRelevantShiftsForVehicles([$vehicleId], $startsAt, $endsAt, $policy);
        $shifts = $shiftsByVehicle->get($vehicleId, collect())
            ->reject(fn (Shift $s) => (int) $s->id === $excludeShiftId);

        return $this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy, $vehicleId, null);
    }

    /**
     * Evaluate availability for a slot given a collection of relevant shifts (no DB queries).
     *
     * @param  array<int, true>|null  $serviceBlockedVehicleIdSet  from {@see fetchServiceBlockedVehicleIdSet}; null = query DB for this vehicle only
     */
    protected function vehicleAvailableForWithShifts(Collection $shifts, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy, ?int $vehicleId = null, ?array $serviceBlockedVehicleIdSet = null): bool
    {
        if ($vehicleId !== null) {
            if ($serviceBlockedVehicleIdSet !== null) {
                if (isset($serviceBlockedVehicleIdSet[$vehicleId])) {
                    return false;
                }
            } elseif ($this->vehicleBlockedByService($vehicleId, $startsAt, $endsAt)) {
                return false;
            }
        }

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

    protected function vehicleBlockedByService(int $vehicleId, Carbon $startsAtUtc, Carbon $endsAtUtc): bool
    {
        return FleetVehicleServiceBlock::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    /**
     * One query: vehicle IDs that have an active service block overlapping [startsAt, endsAt).
     *
     * @param  array<int>  $vehicleIds
     * @return array<int, true>
     */
    protected function fetchServiceBlockedVehicleIdSet(array $vehicleIds, Carbon $startsAtUtc, Carbon $endsAtUtc): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $ids = FleetVehicleServiceBlock::query()
            ->whereIn('fleet_vehicle_id', $vehicleIds)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->pluck('fleet_vehicle_id')
            ->unique()
            ->all();

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * @param  array<int>  $vehicleIds
     */
    protected function fetchServiceBlocksForVehicles(array $vehicleIds, Carbon $windowStartUtc, Carbon $windowEndUtc): Collection
    {
        if ($vehicleIds === []) {
            return collect();
        }

        return FleetVehicleServiceBlock::query()
            ->whereIn('fleet_vehicle_id', $vehicleIds)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $windowEndUtc)
            ->where('ends_at', '>', $windowStartUtc)
            ->get()
            ->groupBy('fleet_vehicle_id');
    }

    /**
     * Get available free slots for a week.
     * One card per vehicle per free window (not unioned across cars at a station).
     * Windows follow that vehicle's booked shifts, downtime, service blocks, and shift policy.
     *
     * @param  int|array<int>|null  $stationIds  Single id, list of ids, or null for all active stations
     * @return array<int, array{id: string, day: string, start: string, end: string, duration: int, station: string, station_short: string, station_address: ?string, station_id: int, date_iso: string, vehicle_id: int, vehicle_tone: int, vehicles: array, cars_count: int}>
     */
    public function getAvailableSlotsForWeek(Carbon $weekStart, array $dayNames, int|array|null $stationIds = null): array
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            return [];
        }
        $stationsQuery = Station::where('is_active', true)->orderBy('name');
        if (is_int($stationIds)) {
            $stationsQuery->where('id', $stationIds);
        } elseif (is_array($stationIds)) {
            $ids = array_values(array_unique(array_map('intval', $stationIds)));
            if ($ids === []) {
                return [];
            }
            $stationsQuery->whereIn('id', $ids);
        }
        $stations = $stationsQuery->get();
        if ($stations->isEmpty()) {
            return [];
        }
        $tz = $policy->timezone ?? 'Europe/Riga';
        $minDurationHours = (float) ($policy->min_duration_hours ?? 4);
        $slotMinutes = $policy->time_slot_minutes ?? 15;
        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);
        $nowInTz = now($tz);

        $weekEnd = $weekStart->copy()->addDays(7)->setTimezone($tz);
        $weekStartUtc = $weekStart->copy()->utc();
        $weekEndUtc = $weekEnd->copy()->utc();
        $vehicles = FleetVehicle::whereIn('home_station_id', $stations->pluck('id'))
            ->where('status', VehicleStatus::Active)
            ->orderBy('id')
            ->get(['id', 'home_station_id', 'brand', 'model', 'registration_number', 'label']);
        $vehiclesByStation = $vehicles->groupBy('home_station_id');
        $vehiclesById = $vehicles->keyBy('id');
        $allVehicleIds = $vehicles->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        if ($allVehicleIds === []) {
            return [];
        }

        // Include shifts that end exactly at week start (e.g. Sun→Mon 00:00): they still need downtime on the first day.
        $shifts = Shift::whereIn('vehicle_id', $allVehicleIds)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '<', $weekEndUtc)
            ->where('ends_at', '>=', $weekStartUtc)
            ->orderBy('vehicle_id')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('vehicle_id');

        $serviceBlocksByVehicle = $this->fetchServiceBlocksForVehicles($allVehicleIds, $weekStartUtc, $weekEndUtc);

        $dayEndMinutes = 24 * 60;
        // freeByDay[dayIndex][vehicleId] = intervals for that vehicle only
        $freeByDay = [];
        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $dayStart = $weekStart->copy()->addDays($dayIndex)->setTimezone($tz)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            $isToday = $dayStart->isSameDay($nowInTz);
            $freeByDay[$dayIndex] = [];
            foreach ($allVehicleIds as $vehicleId) {
                $freeByDay[$dayIndex][$vehicleId] = $this->computeStationFreeIntervals(
                    [$vehicleId],
                    $shifts,
                    $serviceBlocksByVehicle,
                    $dayStart,
                    $dayEnd,
                    $downtimeMinutes,
                    $slotMinutes,
                    $isToday ? $nowInTz : null
                );
            }
        }

        $slots = [];
        $slotId = 0;

        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $dayStart = $weekStart->copy()->addDays($dayIndex)->setTimezone($tz)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            if ($dayEnd->lt($nowInTz)) {
                continue;
            }
            $dateIso = $dayStart->format('Y-m-d');
            $dayName = $dayNames[$dayIndex] ?? 'Day'.($dayIndex + 1);

            foreach ($stations as $station) {
                $stationVehicles = ($vehiclesByStation[$station->id] ?? collect())->values();
                foreach ($stationVehicles as $toneIndex => $vehicle) {
                    $vehicleId = (int) $vehicle->id;
                    $freeIntervals = $freeByDay[$dayIndex][$vehicleId] ?? [];
                    foreach ($freeIntervals as [$startMin, $endMin]) {
                        $bookable = $this->findBookableSameDaySlot(
                            $vehicleId,
                            $shifts,
                            $serviceBlocksByVehicle,
                            $dateIso,
                            $tz,
                            $nowInTz,
                            $startMin,
                            $endMin,
                            $minDurationHours,
                            $slotMinutes,
                            $policy
                        );
                        if ($bookable === null) {
                            continue;
                        }
                        $slots[] = $this->formatVehicleFreeSlot(
                            ++$slotId,
                            $dayName,
                            $bookable['start'],
                            $bookable['end'],
                            $bookable['duration'],
                            $station,
                            $dateIso,
                            $vehicleId,
                            (int) $toneIndex,
                            $vehiclesById,
                            $bookable['end_date_iso']
                        );
                    }

                    if ($dayIndex < 6) {
                        $nextIntervals = $freeByDay[$dayIndex + 1][$vehicleId] ?? [];
                        $nextDayStart = $weekStart->copy()->addDays($dayIndex + 1)->setTimezone($tz)->startOfDay();
                        if (! $nextDayStart->copy()->endOfDay()->lt($nowInTz)) {
                            foreach ($freeIntervals as [$startMin, $endMin]) {
                                if ($endMin < $dayEndMinutes) {
                                    continue;
                                }
                                $tailMinutes = $dayEndMinutes - $startMin;
                                foreach ($nextIntervals as [$nextStart, $nextEnd]) {
                                    if ($nextStart > 0) {
                                        continue;
                                    }
                                    $combinedMinutes = $tailMinutes + $nextEnd;
                                    if ($combinedMinutes < $minDurationHours * 60) {
                                        continue;
                                    }
                                    $bookable = $this->findBookableOvernightSlot(
                                        $vehicleId,
                                        $shifts,
                                        $serviceBlocksByVehicle,
                                        $dateIso,
                                        $nextDayStart->format('Y-m-d'),
                                        $tz,
                                        $nowInTz,
                                        $startMin,
                                        $combinedMinutes,
                                        $tailMinutes,
                                        $minDurationHours,
                                        $policy
                                    );
                                    if ($bookable === null) {
                                        continue;
                                    }
                                    $slots[] = $this->formatVehicleFreeSlot(
                                        ++$slotId,
                                        $dayName,
                                        $bookable['start'],
                                        $bookable['end'],
                                        $bookable['duration'],
                                        $station,
                                        $dateIso,
                                        $vehicleId,
                                        (int) $toneIndex,
                                        $vehiclesById,
                                        $bookable['end_date_iso']
                                    );
                                    break;
                                }
                            }
                        }
                    }

                    $prevDaySkipped = $dayIndex >= 1 && $weekStart->copy()->addDays($dayIndex - 1)->setTimezone($tz)->endOfDay()->lt($nowInTz);
                    if ($dayIndex >= 1 && $prevDaySkipped) {
                        $prevIntervals = $freeByDay[$dayIndex - 1][$vehicleId] ?? [];
                        $prevDayStart = $weekStart->copy()->addDays($dayIndex - 1)->setTimezone($tz)->startOfDay();
                        $prevDateIso = $prevDayStart->format('Y-m-d');
                        foreach ($freeIntervals as [$startMin, $endMin]) {
                            if ($startMin > 0) {
                                continue;
                            }
                            foreach ($prevIntervals as [$prevStart, $prevEnd]) {
                                if ($prevEnd < $dayEndMinutes) {
                                    continue;
                                }
                                $tailMinutes = $dayEndMinutes - $prevStart;
                                $combinedMinutes = $tailMinutes + $endMin;
                                if ($combinedMinutes < $minDurationHours * 60) {
                                    continue;
                                }
                                $bookable = $this->findBookableOvernightSlot(
                                    $vehicleId,
                                    $shifts,
                                    $serviceBlocksByVehicle,
                                    $prevDateIso,
                                    $dateIso,
                                    $tz,
                                    $nowInTz,
                                    $prevStart,
                                    $combinedMinutes,
                                    $tailMinutes,
                                    $minDurationHours,
                                    $policy
                                );
                                if ($bookable === null) {
                                    continue;
                                }
                                if ($bookable['starts_at']->lte($nowInTz)) {
                                    continue;
                                }
                                $slots[] = $this->formatVehicleFreeSlot(
                                    ++$slotId,
                                    $dayName,
                                    $bookable['start'],
                                    $bookable['end'],
                                    $bookable['duration'],
                                    $station,
                                    $prevDateIso,
                                    $vehicleId,
                                    (int) $toneIndex,
                                    $vehiclesById,
                                    $bookable['end_date_iso']
                                );
                                break;
                            }
                        }
                    }
                }
            }
        }

        usort($slots, function (array $a, array $b): int {
            return [$a['date_iso'], $a['start'], $a['station_id'], $a['vehicle_id']]
                <=> [$b['date_iso'], $b['start'], $b['station_id'], $b['vehicle_id']];
        });

        return $slots;
    }

    /**
     * Pick a bookable same-day window inside a free interval.
     * Tries longest duration first (down to min); uses real elapsed minutes and caps at 24h
     * so DST fall-back days (25h calendar length) still produce slots.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Shift>>  $shiftsByVehicle
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, FleetVehicleServiceBlock>>  $serviceBlocksByVehicle
     * @return array{start: string, end: string, duration: int, end_date_iso: ?string}|null
     */
    protected function findBookableSameDaySlot(
        int $vehicleId,
        Collection $shiftsByVehicle,
        Collection $serviceBlocksByVehicle,
        string $dateIso,
        string $tz,
        Carbon $nowInTz,
        int $startMin,
        int $endMin,
        float $minDurationHours,
        int $slotMinutes,
        ShiftPolicy $policy
    ): ?array {
        $intervalHours = ($endMin - $startMin) / 60.0;
        if ($intervalHours < $minDurationHours) {
            return null;
        }

        $minDuration = (int) ceil($minDurationHours);
        $maxDuration = min(24, (int) floor($intervalHours));
        if ($maxDuration < $minDuration) {
            return null;
        }

        $intervalEndsAt = $this->localTimeFromDayMinutes($dateIso, $endMin, $tz);
        $stepMin = $slotMinutes <= 60 ? $slotMinutes : 60;
        $startMinAligned = (int) (floor($startMin / $slotMinutes) * $slotMinutes);

        for ($duration = $maxDuration; $duration >= $minDuration; $duration--) {
            $durationMin = $duration * 60;
            $maxStartMin = $endMin - $durationMin;
            for ($startMinTry = $startMinAligned; $startMinTry <= $maxStartMin; $startMinTry += $stepMin) {
                $slotStartsAt = $this->localTimeFromDayMinutes($dateIso, $startMinTry, $tz);
                if ($slotStartsAt->lte($nowInTz)) {
                    continue;
                }
                $slotEndsAt = $slotStartsAt->copy()->addMinutes($durationMin);
                if ($slotEndsAt->gt($intervalEndsAt)) {
                    $slotEndsAt = $intervalEndsAt->copy();
                }
                $maxEndsAt = $slotStartsAt->copy()->addMinutes(24 * 60);
                if ($slotEndsAt->gt($maxEndsAt)) {
                    $slotEndsAt = $maxEndsAt->copy();
                }
                $actualDuration = (int) floor($slotStartsAt->diffInMinutes($slotEndsAt) / 60);
                if ($actualDuration < $minDuration) {
                    continue;
                }
                if (! $this->vehicleIsAvailableForWindow(
                    $vehicleId,
                    $shiftsByVehicle,
                    $serviceBlocksByVehicle,
                    $slotStartsAt,
                    $slotEndsAt,
                    $policy
                )) {
                    continue;
                }

                $endDateIso = $slotEndsAt->format('Y-m-d') !== $dateIso
                    ? $slotEndsAt->format('Y-m-d')
                    : null;

                return [
                    'start' => $slotStartsAt->format('H:i'),
                    'end' => $slotEndsAt->format('H:i'),
                    'duration' => $actualDuration,
                    'end_date_iso' => $endDateIso,
                ];
            }
        }

        return null;
    }

    /**
     * Overnight free window starting on $startDateIso and ending on $endDateIso.
     * Caps real elapsed time at 24h and steps duration down until bookable.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Shift>>  $shiftsByVehicle
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, FleetVehicleServiceBlock>>  $serviceBlocksByVehicle
     * @return array{start: string, end: string, duration: int, end_date_iso: string, starts_at: Carbon}|null
     */
    protected function findBookableOvernightSlot(
        int $vehicleId,
        Collection $shiftsByVehicle,
        Collection $serviceBlocksByVehicle,
        string $startDateIso,
        string $endDateIso,
        string $tz,
        Carbon $nowInTz,
        int $startMin,
        int $combinedMinutes,
        int $tailMinutes,
        float $minDurationHours,
        ShiftPolicy $policy
    ): ?array {
        $minDuration = (int) ceil($minDurationHours);
        $maxDuration = min(24, (int) floor($combinedMinutes / 60));
        if ($maxDuration < $minDuration) {
            return null;
        }

        $slotStartsAt = $this->localTimeFromDayMinutes($startDateIso, $startMin, $tz);
        if ($slotStartsAt->lte($nowInTz)) {
            return null;
        }

        $nextDayFreeEndMin = $combinedMinutes - $tailMinutes;
        $intervalEndsAt = $this->localTimeFromDayMinutes($endDateIso, $nextDayFreeEndMin, $tz);

        for ($duration = $maxDuration; $duration >= $minDuration; $duration--) {
            $totalMin = $duration * 60;
            if ($totalMin <= $tailMinutes) {
                continue;
            }
            $slotEndsAt = $slotStartsAt->copy()->addMinutes($totalMin);
            if ($slotEndsAt->gt($intervalEndsAt)) {
                $slotEndsAt = $intervalEndsAt->copy();
            }
            $maxEndsAt = $slotStartsAt->copy()->addMinutes(24 * 60);
            if ($slotEndsAt->gt($maxEndsAt)) {
                $slotEndsAt = $maxEndsAt->copy();
            }
            // Must still cross into the next calendar day to count as overnight.
            if ($slotEndsAt->format('Y-m-d') <= $startDateIso) {
                continue;
            }
            $actualDuration = (int) floor($slotStartsAt->diffInMinutes($slotEndsAt) / 60);
            if ($actualDuration < $minDuration) {
                continue;
            }
            if (! $this->vehicleIsAvailableForWindow(
                $vehicleId,
                $shiftsByVehicle,
                $serviceBlocksByVehicle,
                $slotStartsAt,
                $slotEndsAt,
                $policy
            )) {
                continue;
            }

            return [
                'start' => $slotStartsAt->format('H:i'),
                'end' => $slotEndsAt->format('H:i'),
                'duration' => $actualDuration,
                'end_date_iso' => $slotEndsAt->format('Y-m-d'),
                'starts_at' => $slotStartsAt,
            ];
        }

        return null;
    }

    /**
     * Local wall-clock instant for minutes-from-midnight on $dateIso (1440 => next midnight).
     */
    protected function localTimeFromDayMinutes(string $dateIso, int $minutes, string $tz): Carbon
    {
        if ($minutes >= 24 * 60) {
            return Carbon::parse($dateIso, $tz)->startOfDay()->addDay();
        }
        $hours = intdiv(max(0, $minutes), 60);
        $mins = max(0, $minutes) % 60;

        return Carbon::parse(sprintf('%s %02d:%02d', $dateIso, $hours, $mins), $tz);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FleetVehicle>  $vehiclesById
     * @return array{id: string, day: string, start: string, end: string, duration: int, station: string, station_short: string, station_address: ?string, station_id: int, date_iso: string, vehicle_id: int, vehicle_tone: int, vehicles: array, cars_count: int, end_date_iso?: string}
     */
    protected function formatVehicleFreeSlot(
        int $slotId,
        string $dayName,
        string $slotStart,
        string $slotEnd,
        int $suggestedDuration,
        Station $station,
        string $dateIso,
        int $vehicleId,
        int $toneIndex,
        Collection $vehiclesById,
        ?string $endDateIso
    ): array {
        $vehiclesDisplay = $this->formatVehiclesForSlot([$vehicleId], $vehiclesById);
        $slot = [
            'id' => 'as'.$slotId,
            'day' => $dayName,
            'start' => $slotStart,
            'end' => $slotEnd,
            'duration' => $suggestedDuration,
            'station' => $station->name,
            'station_short' => $station->shortLabel(),
            'station_address' => $station->address ?: null,
            'station_id' => (int) $station->id,
            'date_iso' => $dateIso,
            'vehicle_id' => $vehicleId,
            'vehicle_tone' => $toneIndex % 6,
            'vehicles' => $vehiclesDisplay,
            'cars_count' => 1,
        ];
        if ($endDateIso !== null) {
            $slot['end_date_iso'] = $endDateIso;
        }

        return $slot;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, Shift>>  $shiftsByVehicle
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, FleetVehicleServiceBlock>>  $serviceBlocksByVehicle
     */
    protected function vehicleIsAvailableForWindow(
        int $vehicleId,
        Collection $shiftsByVehicle,
        Collection $serviceBlocksByVehicle,
        Carbon $startsAt,
        Carbon $endsAt,
        ShiftPolicy $policy
    ): bool {
        $startsAtUtc = $startsAt->copy()->utc();
        $endsAtUtc = $endsAt->copy()->utc();
        $blocked = false;
        foreach ($serviceBlocksByVehicle->get($vehicleId, collect()) as $block) {
            if ($block->starts_at->lt($endsAtUtc) && $block->ends_at->gt($startsAtUtc)) {
                $blocked = true;
                break;
            }
        }
        $serviceBlockedSet = $blocked ? [$vehicleId => true] : [];
        $shifts = $shiftsByVehicle->get($vehicleId, collect());

        return $this->vehicleAvailableForWithShifts($shifts, $startsAtUtc, $endsAtUtc, $policy, $vehicleId, $serviceBlockedSet);
    }

    /**
     * Debug: return free intervals per day/station and policy so we can see why slots are missing.
     * Use from artisan only (e.g. php artisan slots:debug "Ignitis Jaunmoku").
     *
     * @return array{policy: array, now: string, week_start: string, days: array<int, array{date: string, name: string, skipped: bool, stations: array<int, array{station_name: string, intervals: array}>, slots_count: int}>}
     */
    public function getFreeSlotsDebug(Carbon $weekStart, array $dayNames, ?int $filterStationId = null): array
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            return ['policy' => null, 'now' => '', 'week_start' => $weekStart->toIso8601String(), 'days' => []];
        }
        $tz = $policy->timezone ?? 'Europe/Riga';
        $nowInTz = now($tz);
        $minDurationHours = (float) ($policy->min_duration_hours ?? 4);
        $allowedDurations = $policy->allowedDurations();
        $slotMinutes = $policy->time_slot_minutes ?? 15;
        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);

        $stations = Station::where('is_active', true)->orderBy('name')->get();
        if ($filterStationId) {
            $stations = $stations->where('id', $filterStationId)->values();
        }
        if ($stations->isEmpty()) {
            return [
                'policy' => [
                    'min_duration_hours' => $minDurationHours,
                    'allowed_durations' => $allowedDurations,
                    'time_slot_minutes' => $slotMinutes,
                    'vehicle_downtime_hours' => $policy->vehicle_downtime_hours,
                    'timezone' => $tz,
                ],
                'now' => $nowInTz->toIso8601String(),
                'week_start' => $weekStart->toIso8601String(),
                'days' => [],
            ];
        }

        $vehicles = FleetVehicle::whereIn('home_station_id', $stations->pluck('id'))
            ->where('status', VehicleStatus::Active)
            ->get(['id', 'home_station_id']);
        $vehiclesByStation = $vehicles->groupBy('home_station_id')->map(fn ($v) => $v->pluck('id')->values()->all())->all();
        $allVehicleIds = array_values(array_unique($vehicles->pluck('id')->all()));
        $shifts = [];
        if (! empty($allVehicleIds)) {
            $weekEnd = $weekStart->copy()->addDays(7)->setTimezone($tz);
            $weekStartUtc = $weekStart->copy()->utc();
            $weekEndUtc = $weekEnd->copy()->utc();
            $shifts = Shift::whereIn('vehicle_id', $allVehicleIds)
                ->where('status', ShiftStatus::Booked)
                ->where('starts_at', '<', $weekEndUtc)
                ->where('ends_at', '>=', $weekStartUtc)
                ->orderBy('vehicle_id')
                ->orderBy('starts_at')
                ->get()
                ->groupBy('vehicle_id');
            $serviceBlocksByVehicle = $this->fetchServiceBlocksForVehicles($allVehicleIds, $weekStartUtc, $weekEndUtc);
        } else {
            $serviceBlocksByVehicle = collect();
        }

        $freeByDay = [];
        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $dayStart = $weekStart->copy()->addDays($dayIndex)->setTimezone($tz)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            $isToday = $dayStart->isSameDay($nowInTz);
            $freeByDay[$dayIndex] = [];
            foreach ($stations as $station) {
                $stationVehicleIds = $vehiclesByStation[$station->id] ?? [];
                if (empty($stationVehicleIds)) {
                    continue;
                }
                $freeByDay[$dayIndex][$station->id] = $this->computeStationFreeIntervals(
                    $stationVehicleIds,
                    $shifts,
                    $serviceBlocksByVehicle,
                    $dayStart,
                    $dayEnd,
                    $downtimeMinutes,
                    $slotMinutes,
                    $isToday ? $nowInTz : null
                );
            }
        }

        $slots = $this->getAvailableSlotsForWeek($weekStart, $dayNames);
        $slotsByDayStation = [];
        foreach ($slots as $s) {
            $key = $s['day'].'|'.($s['station_id'] ?? 0);
            $slotsByDayStation[$key] = ($slotsByDayStation[$key] ?? 0) + 1;
        }

        $days = [];
        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $dayStart = $weekStart->copy()->addDays($dayIndex)->setTimezone($tz)->startOfDay();
            $dayEnd = $dayStart->copy()->endOfDay();
            $skipped = $dayEnd->lt($nowInTz);
            $dateIso = $dayStart->format('Y-m-d');
            $dayName = $dayNames[$dayIndex] ?? 'Day'.($dayIndex + 1);
            $stationsDebug = [];
            foreach ($stations as $station) {
                $intervals = $freeByDay[$dayIndex][$station->id] ?? [];
                $stationsDebug[$station->id] = [
                    'station_name' => $station->name,
                    'intervals' => array_map(fn ($iv) => [sprintf('%02d:%02d', (int) floor($iv[0] / 60), $iv[0] % 60), sprintf('%02d:%02d', (int) floor($iv[1] / 60), $iv[1] % 60)], $intervals),
                ];
                $key = $dayName.'|'.$station->id;
                $stationsDebug[$station->id]['slots_count'] = $slotsByDayStation[$key] ?? 0;
            }
            $days[] = [
                'date' => $dateIso,
                'name' => $dayName,
                'skipped' => $skipped,
                'stations' => $stationsDebug,
                'slots_count' => array_sum(array_column($stationsDebug, 'slots_count')),
            ];
        }

        return [
            'policy' => [
                'min_duration_hours' => $minDurationHours,
                'allowed_durations' => $allowedDurations,
                'time_slot_minutes' => $slotMinutes,
                'vehicle_downtime_hours' => $policy->vehicle_downtime_hours,
                'timezone' => $tz,
            ],
            'now' => $nowInTz->toIso8601String(),
            'week_start' => $weekStart->toIso8601String(),
            'days' => $days,
        ];
    }

    /**
     * Which vehicle IDs at a station are available for the given time window.
     *
     * @param  array<int>  $stationVehicleIds
     * @return array<int>
     */
    protected function availableVehicleIdsForSlot(
        int $stationId,
        array $stationVehicleIds,
        \Illuminate\Support\Collection $shiftsByVehicle,
        Carbon $startsAt,
        Carbon $endsAt,
        ShiftPolicy $policy
    ): array {
        $serviceBlockedSet = $this->fetchServiceBlockedVehicleIdSet($stationVehicleIds, $startsAt, $endsAt);
        $availableIds = [];
        foreach ($stationVehicleIds as $vehicleId) {
            $shifts = $shiftsByVehicle->get($vehicleId, collect());
            if ($this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy, $vehicleId, $serviceBlockedSet)) {
                $availableIds[] = $vehicleId;
            }
        }

        return $availableIds;
    }

    /**
     * Format vehicle list for display in free slot: model + registration number.
     *
     * @param  array<int>  $vehicleIds
     * @param  \Illuminate\Support\Collection<int, FleetVehicle>  $vehiclesById
     * @return array<int, array{model: string, number: string|null}>
     */
    protected function formatVehiclesForSlot(array $vehicleIds, \Illuminate\Support\Collection $vehiclesById): array
    {
        $out = [];
        foreach ($vehicleIds as $id) {
            $v = $vehiclesById->get($id);
            if (! $v) {
                continue;
            }
            $model = trim(($v->brand ?? '').' '.($v->model ?? '')) ?: ($v->label ?? '—');
            $out[] = [
                'model' => $model,
                'number' => $v->registration_number ?: null,
            ];
        }

        return $out;
    }

    /**
     * Compute free intervals (in minutes from midnight) for a station on a given day.
     * Union of free intervals across all vehicles. Blocked = shift + downtime + service blocks (no downtime padding).
     *
     * @param  array<int>  $vehicleIds
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, FleetVehicleServiceBlock>>  $serviceBlocksByVehicle
     * @return array<int, array{0: int, 1: int}>
     */
    protected function computeStationFreeIntervals(
        array $vehicleIds,
        \Illuminate\Support\Collection $shiftsByVehicle,
        \Illuminate\Support\Collection $serviceBlocksByVehicle,
        Carbon $dayStart,
        Carbon $dayEnd,
        int $downtimeMinutes,
        int $slotMinutes,
        ?Carbon $cutoffNow
    ): array {
        $dayStartMinutes = 0;
        $dayEndMinutes = 24 * 60;
        $dayStartUtc = $dayStart->copy()->setTimezone('UTC');
        $dayEndUtc = $dayEnd->copy()->setTimezone('UTC');

        $allFreeIntervals = [];

        $nextDayStart = $dayStart->copy()->addDay();

        foreach ($vehicleIds as $vehicleId) {
            // ends_at > dayStart excluded shifts ending exactly at local midnight; those still block 00:00–downtime via padding (endMin=0).
            $vehicleShifts = $shiftsByVehicle->get($vehicleId, collect())
                ->filter(fn (Shift $s) => $s->starts_at->lt($dayEndUtc) && ! $s->ends_at->lt($dayStartUtc))
                ->sortBy('starts_at')
                ->values();

            $blocked = [];
            foreach ($vehicleShifts as $shift) {
                $sStart = $shift->starts_at->copy()->setTimezone($dayStart->timezoneName);
                $sEnd = $shift->ends_at->copy()->setTimezone($dayStart->timezoneName);
                $startMin = (int) round(($sStart->timestamp - $dayStart->timestamp) / 60);
                $endMin = (int) round(($sEnd->timestamp - $dayStart->timestamp) / 60);
                $blockStart = max(0, $startMin - $downtimeMinutes);
                $blockEnd = min(24 * 60, $endMin + $downtimeMinutes);
                if ($blockEnd > $blockStart) {
                    $blocked[] = [$blockStart, $blockEnd];
                }
            }

            // Block end of day only when a shift starts at 00:00 (midnight) next day — downtime before that shift. Do not block when the next shift starts later (e.g. 10:00), so overnight slots can use 23:00–24:00.
            $nextDayStartLocal = $nextDayStart->copy()->setTimezone($dayStart->timezoneName);
            $hasShiftStartingAtMidnightNextDay = $shiftsByVehicle->get($vehicleId, collect())
                ->contains(fn (Shift $s) => $s->starts_at->copy()->setTimezone($dayStart->timezoneName)->equalTo($nextDayStartLocal));
            if ($hasShiftStartingAtMidnightNextDay && $downtimeMinutes > 0) {
                $blockStart = max(0, $dayEndMinutes - $downtimeMinutes);
                $blocked[] = [$blockStart, $dayEndMinutes];
            }

            // Block start of day when a shift ends at 00:00 today (downtime after that shift)
            $hasShiftEndingAtMidnight = $shiftsByVehicle->get($vehicleId, collect())
                ->contains(function (Shift $s) use ($dayStart) {
                    $endLocal = $s->ends_at->copy()->setTimezone($dayStart->timezoneName);

                    return $endLocal->isStartOfDay() && $endLocal->isSameDay($dayStart);
                });
            if ($hasShiftEndingAtMidnight && $downtimeMinutes > 0) {
                $blocked[] = [0, min($downtimeMinutes, $dayEndMinutes)];
            }

            foreach ($serviceBlocksByVehicle->get($vehicleId, collect()) as $block) {
                $bStart = $block->starts_at->copy()->setTimezone($dayStart->timezoneName);
                $bEnd = $block->ends_at->copy()->setTimezone($dayStart->timezoneName);
                if ($bEnd->lte($dayStart) || $bStart->gte($dayEnd)) {
                    continue;
                }
                $clipStart = $bStart->max($dayStart);
                $clipEnd = $bEnd->min($dayEnd);
                $startMin = (int) round(($clipStart->timestamp - $dayStart->timestamp) / 60);
                $endMin = (int) round(($clipEnd->timestamp - $dayStart->timestamp) / 60);
                $blockStart = max(0, $startMin);
                $blockEnd = min($dayEndMinutes, $endMin);
                if ($blockEnd > $blockStart) {
                    $blocked[] = [$blockStart, $blockEnd];
                }
            }

            $free = $this->gapsFromBlocked($dayStartMinutes, $dayEndMinutes, $blocked);
            $allFreeIntervals = array_merge($allFreeIntervals, $free);
        }

        $merged = $this->mergeOverlappingIntervals($allFreeIntervals);

        if ($cutoffNow !== null) {
            $cutoffMinutes = $cutoffNow->hour * 60 + $cutoffNow->minute;
            $cutoffMinutes = (int) (ceil(($cutoffMinutes + 1) / $slotMinutes) * $slotMinutes);
            $merged = array_filter($merged, fn ($iv) => $iv[1] > $cutoffMinutes);
            $merged = array_map(fn ($iv) => [max($iv[0], $cutoffMinutes), $iv[1]], $merged);
        }

        $aligned = [];
        foreach ($merged as [$a, $b]) {
            $aAligned = (int) (floor($a / $slotMinutes) * $slotMinutes);
            $bAligned = (int) (ceil($b / $slotMinutes) * $slotMinutes);
            if ($bAligned > $aAligned) {
                $aligned[] = [$aAligned, $bAligned];
            }
        }

        return array_values($aligned);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $blocked
     * @return array<int, array{0: int, 1: int}>
     */
    protected function gapsFromBlocked(int $dayStart, int $dayEnd, array $blocked): array
    {
        if (empty($blocked)) {
            return [[$dayStart, $dayEnd]];
        }
        usort($blocked, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($blocked as [$a, $b]) {
            if (empty($merged) || $a > $merged[count($merged) - 1][1]) {
                $merged[] = [$a, $b];
            } else {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $b);
            }
        }
        $gaps = [];
        $prevEnd = $dayStart;
        foreach ($merged as [$a, $b]) {
            if ($a > $prevEnd) {
                $gaps[] = [$prevEnd, $a];
            }
            $prevEnd = max($prevEnd, $b);
        }
        if ($prevEnd < $dayEnd) {
            $gaps[] = [$prevEnd, $dayEnd];
        }

        return $gaps;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $intervals
     * @return array<int, array{0: int, 1: int}>
     */
    protected function mergeOverlappingIntervals(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [$intervals[0]];
        for ($i = 1; $i < count($intervals); $i++) {
            $last = &$out[count($out) - 1];
            if ($intervals[$i][0] <= $last[1]) {
                $last[1] = max($last[1], $intervals[$i][1]);
            } else {
                $out[] = $intervals[$i];
            }
        }

        return $out;
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
