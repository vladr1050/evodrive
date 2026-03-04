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
     * Get available free slots for a week. Returns continuous time windows per station per day
     * when at least one vehicle is free. Slots are split only around booked shifts (with downtime).
     * Only windows >= min_duration_hours are shown.
     *
     * @return array<int, array{id: string, day: string, start: string, end: string, duration: int, station: string, station_id: int, date_iso: string}>
     */
    public function getAvailableSlotsForWeek(Carbon $weekStart, array $dayNames): array
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            return [];
        }
        $stations = Station::where('is_active', true)->orderBy('name')->get();
        if ($stations->isEmpty()) {
            return [];
        }
        $tz = $policy->timezone ?? 'Europe/Riga';
        $minDurationHours = (float) ($policy->min_duration_hours ?? 4);
        $allowedDurations = $policy->allowedDurations();
        $slotMinutes = $policy->time_slot_minutes ?? 15;
        $downtimeMinutes = (int) round($policy->vehicle_downtime_hours * 60);
        $nowInTz = now($tz);

        $weekEnd = $weekStart->copy()->addDays(7)->setTimezone($tz);
        $vehicles = FleetVehicle::whereIn('home_station_id', $stations->pluck('id'))
            ->where('status', VehicleStatus::Active)
            ->get(['id', 'home_station_id', 'brand', 'model', 'registration_number', 'label']);
        $vehiclesByStation = $vehicles->groupBy('home_station_id')->map(fn ($v) => $v->pluck('id')->values()->all())->all();
        $vehiclesById = $vehicles->keyBy('id');
        $allVehicleIds = array_unique(array_merge(...array_values($vehiclesByStation)));
        if (empty($allVehicleIds)) {
            return [];
        }

        $shifts = Shift::whereIn('vehicle_id', $allVehicleIds)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '<', $weekEnd)
            ->where('ends_at', '>', $weekStart)
            ->orderBy('vehicle_id')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('vehicle_id');

        $dayEndMinutes = 24 * 60;
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
            $dayName = $dayNames[$dayIndex] ?? 'Day' . ($dayIndex + 1);

            foreach ($stations as $station) {
                $freeIntervals = $freeByDay[$dayIndex][$station->id] ?? [];
                foreach ($freeIntervals as [$startMin, $endMin]) {
                    $durationHours = ($endMin - $startMin) / 60.0;
                    if ($durationHours < $minDurationHours) {
                        continue;
                    }
                    $suggestedDuration = collect($allowedDurations)->filter(fn ($d) => $d <= (int) floor($durationHours))->max() ?? (int) floor($durationHours);
                    if ($suggestedDuration < $minDurationHours) {
                        continue;
                    }
                    $h1 = (int) floor($startMin / 60);
                    $m1 = $startMin % 60;
                    $h2 = (int) floor($endMin / 60);
                    $m2 = $endMin % 60;
                    $slotStart = sprintf('%02d:%02d', $h1, $m1);
                    $slotEnd = sprintf('%02d:%02d', $h2, $m2);
                    $slotStartsAt = Carbon::parse($dateIso . ' ' . $slotStart, $tz);
                    $slotEndsAt = Carbon::parse($dateIso . ' ' . $slotEnd, $tz);
                    if ($slotEndsAt->lte($slotStartsAt)) {
                        $slotEndsAt->addDay();
                    }
                    $availableVehicleIds = $this->availableVehicleIdsForSlot(
                        $station->id,
                        $vehiclesByStation[$station->id] ?? [],
                        $shifts,
                        $slotStartsAt,
                        $slotEndsAt,
                        $policy
                    );
                    $vehiclesDisplay = $this->formatVehiclesForSlot($availableVehicleIds, $vehiclesById);
                    $slots[] = [
                        'id' => 'as' . (++$slotId),
                        'day' => $dayName,
                        'start' => $slotStart,
                        'end' => $slotEnd,
                        'duration' => $suggestedDuration,
                        'station' => $station->name,
                        'station_id' => $station->id,
                        'date_iso' => $dateIso,
                        'vehicles' => $vehiclesDisplay,
                    ];
                }

                if ($dayIndex < 6) {
                    $nextIntervals = $freeByDay[$dayIndex + 1][$station->id] ?? [];
                    $nextDayStart = $weekStart->copy()->addDays($dayIndex + 1)->setTimezone($tz)->startOfDay();
                    if ($nextDayStart->copy()->endOfDay()->lt($nowInTz)) {
                        continue;
                    }
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
                            $maxDuration = (int) floor($combinedMinutes / 60);
                            $suggestedDuration = collect($allowedDurations)->filter(fn ($d) => $d <= $maxDuration && $d >= (int) $minDurationHours)->max();
                            if ($suggestedDuration === null || $suggestedDuration < $minDurationHours) {
                                continue;
                            }
                            $totalMin = $suggestedDuration * 60;
                            if ($totalMin <= $tailMinutes) {
                                continue;
                            }
                            $endMinNext = $totalMin - $tailMinutes;
                            $h1 = (int) floor($startMin / 60);
                            $m1 = $startMin % 60;
                            $h2 = (int) floor($endMinNext / 60);
                            $m2 = $endMinNext % 60;
                            $slotStart = sprintf('%02d:%02d', $h1, $m1);
                            $slotEnd = sprintf('%02d:%02d', $h2, $m2);
                            $slotStartsAt = Carbon::parse($dateIso . ' ' . $slotStart, $tz);
                            $slotEndsAt = Carbon::parse($nextDayStart->format('Y-m-d') . ' ' . $slotEnd, $tz);
                            $availableVehicleIds = $this->availableVehicleIdsForSlot(
                                $station->id,
                                $vehiclesByStation[$station->id] ?? [],
                                $shifts,
                                $slotStartsAt,
                                $slotEndsAt,
                                $policy
                            );
                            $vehiclesDisplay = $this->formatVehiclesForSlot($availableVehicleIds, $vehiclesById);
                            $slots[] = [
                                'id' => 'as' . (++$slotId),
                                'day' => $dayName,
                                'start' => $slotStart,
                                'end' => $slotEnd,
                                'duration' => $suggestedDuration,
                                'station' => $station->name,
                                'station_id' => $station->id,
                                'date_iso' => $dateIso,
                                'end_date_iso' => $nextDayStart->format('Y-m-d'),
                                'vehicles' => $vehiclesDisplay,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $slots;
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
        $durationHours = $startsAt->diffInMinutes($endsAt) / 60.0;
        $availableIds = [];
        foreach ($stationVehicleIds as $vehicleId) {
            $shifts = $shiftsByVehicle->get($vehicleId, collect());
            if ($this->vehicleAvailableForWithShifts($shifts, $startsAt, $endsAt, $policy)) {
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
            $model = trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) ?: ($v->label ?? '—');
            $out[] = [
                'model' => $model,
                'number' => $v->registration_number ?: null,
            ];
        }
        return $out;
    }

    /**
     * Compute free intervals (in minutes from midnight) for a station on a given day.
     * Union of free intervals across all vehicles. Blocked = shift + downtime.
     *
     * @param  array<int>  $vehicleIds
     * @return array<int, array{0: int, 1: int}>
     */
    protected function computeStationFreeIntervals(
        array $vehicleIds,
        \Illuminate\Support\Collection $shiftsByVehicle,
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

        foreach ($vehicleIds as $vehicleId) {
            $vehicleShifts = $shiftsByVehicle->get($vehicleId, collect())
                ->filter(fn (Shift $s) => $s->starts_at->lt($dayEndUtc) && $s->ends_at->gt($dayStartUtc))
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
