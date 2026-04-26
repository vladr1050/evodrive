<?php

namespace App\Services\Utilization;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Fleet-level driver metrics for a date range: full driver roster (zeros),
 * medians, future booked load, cancellations, and a transparent activity score.
 */
final class DriverFleetInsightsService
{
    /**
     * @param  Collection<int, object>  $dailyUtilizationRows  from {@see DriverUtilizationService::getDailyDriverUtilization}
     * @param  array<int>  $fleetDriverIds  drivers to include (same order as UI roster)
     * @return object{
     *   rows: Collection<int, object>,
     *   median_worked_hours: float,
     *   median_booked_in_range_hours: float,
     *   median_future_booked_hours: float,
     *   day_count: int,
     *   future_horizon_days: int
     * }
     */
    public function build(
        Collection $dailyUtilizationRows,
        DateRange $range,
        DriverUtilizationFilters $filters,
        array $fleetDriverIds,
        string $tz,
        int $futureHorizonDays = 30,
    ): object {
        $fleetDriverIds = array_values(array_unique(array_map('intval', $fleetDriverIds)));
        $dayCount = max(1, count($range->dateKeys()));

        $totalsFromRows = $this->aggregateTotalsFromDailyRows($dailyUtilizationRows);
        $shiftDays = $this->distinctShiftDaysFromDailyRows($dailyUtilizationRows);

        $cancelledByDriver = $this->cancelledHoursByDriver($range, $filters, $tz, $fleetDriverIds);
        $futureBookedByDriver = $this->futureBookedHoursByDriver($filters, $tz, $fleetDriverIds, $futureHorizonDays);
        $firstShiftByDriver = $this->firstShiftStartedAtByDriver($fleetDriverIds, $filters);
        $completedEver = $this->driversWithCompletedShift($fleetDriverIds, $filters);

        $names = Driver::query()
            ->whereIn('id', $fleetDriverIds)
            ->get()
            ->keyBy('id')
            ->map(fn (Driver $d) => $d->name);

        $workedList = [];
        $bookedRangeList = [];
        $futureList = [];
        foreach ($fleetDriverIds as $id) {
            $t = $totalsFromRows[$id] ?? ['worked' => 0, 'booked' => 0, 'total' => 0];
            $workedList[] = round($t['worked'] / 60, 4);
            $bookedRangeList[] = round($t['booked'] / 60, 4);
            $futureList[] = round(($futureBookedByDriver[$id] ?? 0) / 60, 4);
        }

        $medianWorked = $this->median($workedList);
        $medianBookedRange = $this->median($bookedRangeList);
        $medianFuture = $this->median($futureList);

        $rows = collect();
        foreach ($fleetDriverIds as $id) {
            $t = $totalsFromRows[$id] ?? ['worked' => 0, 'booked' => 0, 'total' => 0];
            $workedH = round($t['worked'] / 60, 1);
            $bookedH = round($t['booked'] / 60, 1);
            $totalH = round($t['total'] / 60, 1);
            $futureMinutes = (int) ($futureBookedByDriver[$id] ?? 0);
            $futureH = round($futureMinutes / 60, 1);
            $cancelMinutes = (int) ($cancelledByDriver[$id] ?? 0);
            $cancelH = round($cancelMinutes / 60, 1);
            $firstAt = $firstShiftByDriver[$id] ?? null;
            $hasCompleted = in_array($id, $completedEver, true);
            $isNovice = $this->isNovice($firstAt, $hasCompleted);

            $vsMedian = round($workedH - $medianWorked, 1);
            $band = $this->medianBand($workedH, $medianWorked);

            $workedC = $this->componentWorkedVsMedian($workedH, $medianWorked);
            $forwardC = $this->componentForwardVsMedian($futureH, $medianFuture);
            $reliabilityC = $this->componentReliability($cancelMinutes, $t['worked'] + $t['booked']);

            $activityScore = $isNovice
                ? null
                : (int) round(0.45 * $workedC + 0.35 * $forwardC + 0.2 * $reliabilityC);

            $rows->push((object) [
                'driver_id' => $id,
                'driver_name' => (string) $names->get($id, '—'),
                'worked_hours' => $workedH,
                'booked_hours' => $bookedH,
                'total_hours' => $totalH,
                'future_booked_hours' => $futureH,
                'cancelled_hours' => $cancelH,
                'shift_days_in_range' => (int) ($shiftDays[$id] ?? 0),
                'first_shift_at' => $firstAt,
                'has_completed_history' => $hasCompleted,
                'is_novice' => $isNovice,
                'vs_median_worked' => $vsMedian,
                'median_band' => $band,
                'activity_score' => $activityScore,
                'score_worked_component' => $isNovice ? null : $workedC,
                'score_forward_component' => $isNovice ? null : $forwardC,
                'score_reliability_component' => $isNovice ? null : $reliabilityC,
            ]);
        }

        // Novices first; then everyone else by activity score descending (highest at top), name tie-break.
        $rows = $rows->sortBy(fn ($r) => $r->is_novice
            ? [0, $r->driver_name]
            : [1, -($r->activity_score ?? 0), $r->driver_name]
        )->values();

        return (object) [
            'rows' => $rows,
            'median_worked_hours' => round($medianWorked, 1),
            'median_booked_in_range_hours' => round($medianBookedRange, 1),
            'median_future_booked_hours' => round($medianFuture, 1),
            'day_count' => $dayCount,
            'future_horizon_days' => $futureHorizonDays,
        ];
    }

    /**
     * Active drivers for fleet roster when the UI means “all drivers”.
     *
     * @return array<int>
     */
    public function defaultFleetDriverIds(): array
    {
        return Driver::query()
            ->orderBy('name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<int, object>  $dailyUtilizationRows
     * @return array<int, array{worked: int, booked: int, total: int}>
     */
    private function aggregateTotalsFromDailyRows(Collection $dailyUtilizationRows): array
    {
        $by = [];
        foreach ($dailyUtilizationRows as $r) {
            $id = (int) $r->driver_id;
            if (! isset($by[$id])) {
                $by[$id] = ['worked' => 0, 'booked' => 0, 'total' => 0];
            }
            $by[$id]['worked'] += (int) $r->worked_minutes;
            $by[$id]['booked'] += (int) $r->planned_minutes;
            $by[$id]['total'] += (int) $r->total_minutes;
        }

        return $by;
    }

    /**
     * @param  Collection<int, object>  $dailyUtilizationRows
     * @return array<int, int>
     */
    private function distinctShiftDaysFromDailyRows(Collection $dailyUtilizationRows): array
    {
        $by = [];
        foreach ($dailyUtilizationRows as $r) {
            $id = (int) $r->driver_id;
            if (((int) $r->total_minutes + (int) $r->planned_minutes + (int) $r->worked_minutes) <= 0) {
                continue;
            }
            if (! isset($by[$id])) {
                $by[$id] = [];
            }
            $by[$id][$r->date] = true;
        }

        $out = [];
        foreach ($by as $id => $dates) {
            $out[$id] = count($dates);
        }

        return $out;
    }

    /**
     * @return array<int, int> driver_id => overlap minutes in range
     */
    private function cancelledHoursByDriver(DateRange $range, DriverUtilizationFilters $filters, string $tz, array $fleetDriverIds): array
    {
        if ($fleetDriverIds === []) {
            return [];
        }

        $from = Carbon::parse($range->dateFrom, $tz)->startOfDay()->utc();
        $to = Carbon::parse($range->dateTo, $tz)->endOfDay()->utc();

        $query = Shift::query()
            ->where('status', ShiftStatus::Cancelled)
            ->whereIn('driver_id', $fleetDriverIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);

        $this->applyStationVehicleFilters($query, $filters);

        $out = array_fill_keys($fleetDriverIds, 0);
        foreach ($query->get(['driver_id', 'starts_at', 'ends_at']) as $shift) {
            $id = (int) $shift->driver_id;
            $start = $shift->starts_at->copy()->max($from);
            $end = $shift->ends_at->copy()->min($to);
            if ($start->lt($end)) {
                $out[$id] = ($out[$id] ?? 0) + (int) $start->diffInMinutes($end);
            }
        }

        return $out;
    }

    /**
     * Booked shifts overlapping [today, today + horizon] in local TZ.
     *
     * @return array<int, int> driver_id => minutes
     */
    private function futureBookedHoursByDriver(DriverUtilizationFilters $filters, string $tz, array $fleetDriverIds, int $horizonDays): array
    {
        if ($fleetDriverIds === []) {
            return [];
        }

        $from = Carbon::now($tz)->startOfDay()->utc();
        $to = Carbon::now($tz)->addDays($horizonDays)->endOfDay()->utc();

        $query = Shift::query()
            ->where('status', ShiftStatus::Booked)
            ->whereIn('driver_id', $fleetDriverIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);

        $this->applyStationVehicleFilters($query, $filters);

        $out = array_fill_keys($fleetDriverIds, 0);
        foreach ($query->get(['driver_id', 'starts_at', 'ends_at']) as $shift) {
            $id = (int) $shift->driver_id;
            $start = $shift->starts_at->copy()->max($from);
            $end = $shift->ends_at->copy()->min($to);
            if ($start->lt($end)) {
                $out[$id] = ($out[$id] ?? 0) + (int) $start->diffInMinutes($end);
            }
        }

        return $out;
    }

    /**
     * @return array<int, \Carbon\Carbon|null>
     */
    private function firstShiftStartedAtByDriver(array $fleetDriverIds, DriverUtilizationFilters $filters): array
    {
        if ($fleetDriverIds === []) {
            return [];
        }

        $query = Shift::query()
            ->whereIn('driver_id', $fleetDriverIds)
            ->whereNotNull('driver_id');
        $this->applyStationVehicleFilters($query, $filters);

        $out = array_fill_keys($fleetDriverIds, null);
        $rows = (clone $query)
            ->selectRaw('driver_id, MIN(starts_at) as first_at')
            ->groupBy('driver_id')
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->driver_id;
            if ($row->first_at !== null) {
                $out[$id] = Carbon::parse($row->first_at);
            }
        }

        return $out;
    }

    /**
     * @return list<int> driver ids with at least one completed shift (any time), respecting station/vehicle filters on that shift row
     */
    private function driversWithCompletedShift(array $fleetDriverIds, DriverUtilizationFilters $filters): array
    {
        if ($fleetDriverIds === []) {
            return [];
        }

        $query = Shift::query()
            ->where('status', ShiftStatus::Completed)
            ->whereIn('driver_id', $fleetDriverIds);

        $this->applyStationVehicleFilters($query, $filters);

        return $query->clone()->distinct()->pluck('driver_id')->map(fn ($id) => (int) $id)->all();
    }

    private function applyStationVehicleFilters(Builder $query, DriverUtilizationFilters $filters): void
    {
        if ($filters->stationIds !== null && $filters->stationIds !== []) {
            $query->whereIn('station_id', $filters->stationIds);
        }
        if ($filters->vehicleIds !== null && $filters->vehicleIds !== []) {
            $vehicleIds = array_map('intval', $filters->vehicleIds);
            $query->where(function (Builder $q) use ($vehicleIds, $filters) {
                $q->whereIn('vehicle_id', $vehicleIds);
                if ($filters->attributeBookedShiftsToOriginalVehicle) {
                    $q->orWhere(function (Builder $q2) use ($vehicleIds) {
                        $q2->whereIn('original_vehicle_id', $vehicleIds)
                            ->where('status', ShiftStatus::Booked);
                    });
                }
            });
        }
    }

    /**
     * No completed shifts in history (respecting filters) → no activity score; avoids penalising unknowns.
     * Tenure is still shown in the UI via {@see $firstShiftAt}.
     */
    private function isNovice(?Carbon $firstShiftAt, bool $hasCompletedHistory): bool
    {
        if (! $hasCompletedHistory) {
            return true;
        }

        return $firstShiftAt === null;
    }

    private function medianBand(float $worked, float $median): string
    {
        if ($median <= 0.01) {
            return $worked > 0.01 ? 'above_median' : 'at_median';
        }
        $low = $median * 0.85;
        $high = $median * 1.15;
        if ($worked < $low) {
            return 'below_median';
        }
        if ($worked > $high) {
            return 'above_median';
        }

        return 'at_median';
    }

    private function componentWorkedVsMedian(float $workedH, float $medianH): int
    {
        if ($medianH <= 0.01) {
            return $workedH > 0 ? 100 : 50;
        }

        return (int) min(100, round(100 * $workedH / $medianH));
    }

    private function componentForwardVsMedian(float $futureH, float $medianFutureH): int
    {
        if ($medianFutureH <= 0.01) {
            return $futureH > 0 ? 100 : 50;
        }

        return (int) min(100, round(100 * $futureH / $medianFutureH));
    }

    /**
     * Penalise cancelled time vs booked+worked volume in the analysis range.
     */
    private function componentReliability(int $cancelMinutes, int $workedPlusBookedMinutes): int
    {
        $denom = max(60, $workedPlusBookedMinutes + 60);
        $ratio = $cancelMinutes / $denom;

        return (int) max(0, min(100, round(100 * (1 - min(1.0, $ratio * 2)))));
    }

    /**
     * @param  list<float|int>  $numbers
     */
    private function median(array $numbers): float
    {
        $numbers = array_values(array_filter($numbers, static fn ($v) => is_numeric($v)));
        sort($numbers);
        $c = count($numbers);
        if ($c === 0) {
            return 0.0;
        }
        $mid = intdiv($c, 2);
        if ($c % 2 === 1) {
            return (float) $numbers[$mid];
        }

        return ((float) $numbers[$mid - 1] + (float) $numbers[$mid]) / 2.0;
    }
}
