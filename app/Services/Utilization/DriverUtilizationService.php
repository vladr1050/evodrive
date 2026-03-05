<?php

namespace App\Services\Utilization;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Driver utilization: hours per driver per day from shifts.
 * planned_minutes = booked; worked_minutes = completed; total_minutes = merged (no double-count), cap 24h.
 * Splits across midnight.
 */
class DriverUtilizationService
{
    private const MINUTES_PER_DAY = 1440;

    public function __construct(
        private ?string $defaultTimezone = null
    ) {
        $this->defaultTimezone = $defaultTimezone ?? ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
    }

    /**
     * Daily utilization per driver for the range.
     *
     * @return Collection<int, object{date: string, driver_id: int, driver_name: string, planned_minutes: int, worked_minutes: int, total_minutes: int, total_hours: float, stations: array<string>, vehicles: array<string>}>
     */
    public function getDailyDriverUtilization(DateRange $range, DriverUtilizationFilters $filters): Collection
    {
        $tz = $filters->timezone ?? $this->defaultTimezone;
        $shifts = $this->loadShiftsInRange($range, $filters, $tz);

        // (date => (driver_id => { planned: [], worked: [], all: [], stations: Set, vehicles: Set }))
        $byDateDriver = [];

        foreach ($shifts as $shift) {
            if (! $shift->driver_id) {
                continue;
            }
            $shiftStart = $shift->starts_at->copy()->setTimezone($tz);
            $shiftEnd = $shift->ends_at->copy()->setTimezone($tz);
            $day = $shiftStart->copy()->startOfDay();
            $status = $shift->status;
            $isBooked = $status === ShiftStatus::Booked;
            $isCompleted = $status === ShiftStatus::Completed;
            $stationName = $shift->station?->name ?? '—';
            $vehicleLabel = $shift->vehicle
                ? trim($shift->vehicle->brand . ' ' . $shift->vehicle->model) . ' (' . ($shift->vehicle->registration_number ?? '—') . ')'
                : '—';

            while ($day->lte($shiftEnd)) {
                $dayStart = $day->copy()->startOfDay();
                $dayEndThis = $day->copy()->endOfDay();
                $overlapStart = $shiftStart->copy()->max($dayStart);
                $overlapEnd = $shiftEnd->copy()->min($dayEndThis);
                if ($overlapStart->lt($overlapEnd)) {
                    $startMin = (int) round($dayStart->diffInMinutes($overlapStart, false));
                    $endMin = (int) round($dayStart->diffInMinutes($overlapEnd, false));
                    $dateKey = $day->format('Y-m-d');
                    if (! isset($byDateDriver[$dateKey][$shift->driver_id])) {
                        $byDateDriver[$dateKey][$shift->driver_id] = [
                            'planned' => [],
                            'worked' => [],
                            'all' => [],
                            'stations' => [],
                            'vehicles' => [],
                        ];
                    }
                    $interval = [$startMin, $endMin];
                    $byDateDriver[$dateKey][$shift->driver_id]['all'][] = $interval;
                    if ($isBooked) {
                        $byDateDriver[$dateKey][$shift->driver_id]['planned'][] = $interval;
                    }
                    if ($isCompleted) {
                        $byDateDriver[$dateKey][$shift->driver_id]['worked'][] = $interval;
                    }
                    if (! in_array($stationName, $byDateDriver[$dateKey][$shift->driver_id]['stations'], true)) {
                        $byDateDriver[$dateKey][$shift->driver_id]['stations'][] = $stationName;
                    }
                    if (! in_array($vehicleLabel, $byDateDriver[$dateKey][$shift->driver_id]['vehicles'], true)) {
                        $byDateDriver[$dateKey][$shift->driver_id]['vehicles'][] = $vehicleLabel;
                    }
                }
                $day->addDay();
            }
        }

        $driverNames = $this->loadDriverNames($shifts);
        $rows = [];
        foreach ($byDateDriver as $dateKey => $byDriver) {
            foreach ($byDriver as $driverId => $data) {
                $plannedMinutes = $this->rawMinutes($data['planned']);
                $workedMinutes = $this->rawMinutes($data['worked']);
                $totalMinutes = min(self::MINUTES_PER_DAY, $this->mergedMinutes($data['all']));
                $rows[] = (object) [
                    'date' => $dateKey,
                    'driver_id' => $driverId,
                    'driver_name' => $driverNames->get($driverId, '—'),
                    'planned_minutes' => $plannedMinutes,
                    'worked_minutes' => $workedMinutes,
                    'total_minutes' => $totalMinutes,
                    'total_hours' => round($totalMinutes / 60, 2),
                    'stations' => $data['stations'],
                    'vehicles' => $data['vehicles'],
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$b->date, $a->driver_name] <=> [$a->date, $b->driver_name]);

        return collect($rows);
    }

    /**
     * Shifts contributing to one driver on one day (for drilldown modal).
     *
     * @return array<int, array{shift_id: int, station: string, vehicle: string, start: string, end: string, duration_minutes: int, status: string}>
     */
    public function getDriverDayBreakdown(int $driverId, string $date, DriverUtilizationFilters $filters): array
    {
        $tz = $filters->timezone ?? $this->defaultTimezone;
        $range = new DateRange($date, $date);
        $shifts = $this->loadShiftsInRange($range, $filters, $tz)->filter(fn (Shift $s) => $s->driver_id === $driverId);
        $dayStart = Carbon::parse($date, $tz)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();
        $result = [];

        foreach ($shifts as $shift) {
            $shiftStart = $shift->starts_at->copy()->setTimezone($tz);
            $shiftEnd = $shift->ends_at->copy()->setTimezone($tz);
            $overlapStart = $shiftStart->copy()->max($dayStart);
            $overlapEnd = $shiftEnd->copy()->min($dayEnd);
            if ($overlapStart->lt($overlapEnd)) {
                $minutes = (int) round($overlapStart->diffInMinutes($overlapEnd));
                $result[] = [
                    'shift_id' => $shift->id,
                    'station' => $shift->station?->name ?? '—',
                    'vehicle' => $shift->vehicle
                        ? trim($shift->vehicle->brand . ' ' . $shift->vehicle->model) . ' ' . ($shift->vehicle->registration_number ?? '')
                        : '—',
                    'start' => $overlapStart->format('H:i'),
                    'end' => $overlapEnd->format('H:i'),
                    'duration_minutes' => $minutes,
                    'status' => $shift->status->value,
                ];
            }
        }

        return $result;
    }

    private function rawMinutes(array $intervals): int
    {
        $total = 0;
        foreach ($intervals as [$a, $b]) {
            $total += max(0, min($b, self::MINUTES_PER_DAY) - max($a, 0));
        }

        return $total;
    }

    private function mergedMinutes(array $intervals): int
    {
        if (empty($intervals)) {
            return 0;
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as [$a, $b]) {
            if (empty($merged) || $a > $merged[count($merged) - 1][1]) {
                $merged[] = [$a, $b];
            } else {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $b);
            }
        }
        $total = 0;
        foreach ($merged as [$a, $b]) {
            $total += max(0, min($b, self::MINUTES_PER_DAY) - max($a, 0));
        }

        return min($total, self::MINUTES_PER_DAY);
    }

    private function loadShiftsInRange(DateRange $range, DriverUtilizationFilters $filters, string $tz): Collection
    {
        $from = Carbon::parse($range->dateFrom, $tz)->startOfDay()->setTimezone('UTC');
        $to = Carbon::parse($range->dateTo, $tz)->endOfDay()->setTimezone('UTC');

        $query = Shift::query()
            ->with(['driver', 'vehicle', 'station'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at');

        if ($filters->includeBooked() && $filters->includeCompleted()) {
            $query->whereIn('status', [ShiftStatus::Booked, ShiftStatus::Completed]);
        } elseif ($filters->includeBooked()) {
            $query->where('status', ShiftStatus::Booked);
        } elseif ($filters->includeCompleted()) {
            $query->where('status', ShiftStatus::Completed);
        } else {
            return collect();
        }

        if ($filters->driverIds !== null && $filters->driverIds !== []) {
            $query->whereIn('driver_id', $filters->driverIds);
        }
        if ($filters->stationIds !== null && $filters->stationIds !== []) {
            $query->whereIn('station_id', $filters->stationIds);
        }
        if ($filters->vehicleIds !== null && $filters->vehicleIds !== []) {
            $query->whereIn('vehicle_id', $filters->vehicleIds);
        }

        return $query->get();
    }

    private function loadDriverNames(Collection $shifts): Collection
    {
        $ids = $shifts->pluck('driver_id')->filter()->unique()->values()->all();
        if (empty($ids)) {
            return collect();
        }

        return Driver::whereIn('id', $ids)->get()->keyBy('id')->map(fn (Driver $d) => $d->name);
    }
}
