<?php

namespace App\Services\Utilization;

use App\Enums\ShiftStatus;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Fleet utilization: hours per vehicle per day from shifts (source: shifts table).
 * Splits across midnight; merges overlapping intervals for total; caps at 24h.
 */
class VehicleUtilizationService
{
    private const MINUTES_PER_DAY = 1440;

    public function __construct(
        private ?string $defaultTimezone = null
    ) {
        $this->defaultTimezone = $defaultTimezone ?? ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
    }

    /**
     * Daily utilization per vehicle for the range. Total = merged intervals (no double-count), cap 24h.
     *
     * @return Collection<int, object{date: string, vehicle_id: int, vehicle_name: string, booked_minutes: int, completed_minutes: int, total_minutes: int, total_hours: float}>
     */
    public function getDailyUtilization(DateRange $range, UtilizationFilters $filters): Collection
    {
        $tz = $filters->timezone ?? $this->defaultTimezone;
        $shifts = $this->loadShiftsInRange($range, $filters, $tz);
        $vehicles = $this->loadVehicles($shifts);

        // (date => (vehicle_id => { booked: [intervals], completed: [intervals], all: [intervals] }))
        $byDateVehicle = [];

        foreach ($shifts as $shift) {
            if (! $shift->vehicle_id) {
                continue;
            }
            $shiftStart = $shift->starts_at->copy()->setTimezone($tz);
            $shiftEnd = $shift->ends_at->copy()->setTimezone($tz);
            $day = $shiftStart->copy()->startOfDay();
            $status = $shift->status;
            $isBooked = $status === ShiftStatus::Booked;
            $isCompleted = $status === ShiftStatus::Completed;
            $bucketVehicleId = $this->utilizationVehicleBucketId($shift, $isBooked, $isCompleted, $filters);
            if ($bucketVehicleId === null) {
                continue;
            }

            while ($day->lte($shiftEnd)) {
                $dayStart = $day->copy()->startOfDay();
                $dayEndThis = $day->copy()->endOfDay();
                $overlapStart = $shiftStart->copy()->max($dayStart);
                $overlapEnd = $shiftEnd->copy()->min($dayEndThis);
                if ($overlapStart->lt($overlapEnd)) {
                    $startMin = (int) round($dayStart->diffInMinutes($overlapStart, false));
                    $endMin = (int) round($dayStart->diffInMinutes($overlapEnd, false));
                    $dateKey = $day->format('Y-m-d');
                    if (! isset($byDateVehicle[$dateKey][$bucketVehicleId])) {
                        $byDateVehicle[$dateKey][$bucketVehicleId] = [
                            'booked' => [],
                            'completed' => [],
                            'all' => [],
                        ];
                    }
                    $interval = [$startMin, $endMin];
                    $byDateVehicle[$dateKey][$bucketVehicleId]['all'][] = $interval;
                    if ($isBooked) {
                        $byDateVehicle[$dateKey][$bucketVehicleId]['booked'][] = $interval;
                    }
                    if ($isCompleted) {
                        $byDateVehicle[$dateKey][$bucketVehicleId]['completed'][] = $interval;
                    }
                }
                $day->addDay();
            }
        }

        $rows = [];
        foreach ($byDateVehicle as $dateKey => $byVehicle) {
            foreach ($byVehicle as $vehicleId => $data) {
                $bookedMinutes = $this->rawMinutes($data['booked']);
                $completedMinutes = $this->rawMinutes($data['completed']);
                $totalMinutes = min(self::MINUTES_PER_DAY, $this->mergedMinutes($data['all']));
                $rows[] = (object) [
                    'date' => $dateKey,
                    'vehicle_id' => $vehicleId,
                    'vehicle_name' => $vehicles->get($vehicleId, '—'),
                    'booked_minutes' => $bookedMinutes,
                    'completed_minutes' => $completedMinutes,
                    'total_minutes' => $totalMinutes,
                    'total_hours' => round($totalMinutes / 60, 2),
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$b->date, $a->vehicle_name] <=> [$a->date, $b->vehicle_name]);

        return collect($rows);
    }

    /**
     * Intervals contributing to utilization for one vehicle on one day (split, not merged).
     *
     * @return array<int, array{source_type: string, source_id: int, status: string, start: string, end: string, minutes: int}>
     */
    public function getDailyIntervals(int $vehicleId, string $date, UtilizationFilters $filters): array
    {
        $tz = $filters->timezone ?? $this->defaultTimezone;
        $range = new DateRange($date, $date);
        $shifts = $this->loadShiftsInRange($range, $filters, $tz)->filter(
            fn (Shift $s) => $this->shiftMatchesVehicleFilter($s, $vehicleId, $filters)
        );
        $dayStart = Carbon::parse($date, $tz)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();
        $intervals = [];

        foreach ($shifts as $shift) {
            $shiftStart = $shift->starts_at->copy()->setTimezone($tz);
            $shiftEnd = $shift->ends_at->copy()->setTimezone($tz);
            $overlapStart = $shiftStart->copy()->max($dayStart);
            $overlapEnd = $shiftEnd->copy()->min($dayEnd);
            if ($overlapStart->lt($overlapEnd)) {
                $minutes = (int) round($overlapStart->diffInMinutes($overlapEnd));
                $intervals[] = [
                    'source_type' => 'shift',
                    'source_id' => $shift->id,
                    'status' => $shift->status->value,
                    'start' => $overlapStart->toIso8601String(),
                    'end' => $overlapEnd->toIso8601String(),
                    'minutes' => $minutes,
                ];
            }
        }

        return $intervals;
    }

    /**
     * Raw shifts in range (for API / debugging).
     *
     * @return Collection<int, Shift>
     */
    public function getSourcesInRange(DateRange $range, UtilizationFilters $filters): Collection
    {
        $tz = $filters->timezone ?? $this->defaultTimezone;

        return $this->loadShiftsInRange($range, $filters, $tz);
    }

    /**
     * Sum of interval lengths (no merge). Used for booked/completed display.
     *
     * @param  array<int, array{0: int, 1: int}>  $intervals
     */
    private function rawMinutes(array $intervals): int
    {
        $total = 0;
        foreach ($intervals as [$a, $b]) {
            $total += max(0, min($b, self::MINUTES_PER_DAY) - max($a, 0));
        }

        return $total;
    }

    /**
     * Merge overlapping intervals and return total minutes (cap at MINUTES_PER_DAY).
     *
     * @param  array<int, array{0: int, 1: int}>  $intervals  [startMin, endMin] in 0..1440
     */
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

    private function loadShiftsInRange(DateRange $range, UtilizationFilters $filters, string $tz): Collection
    {
        $from = Carbon::parse($range->dateFrom, $tz)->startOfDay()->setTimezone('UTC');
        $to = Carbon::parse($range->dateTo, $tz)->endOfDay()->setTimezone('UTC');

        $query = Shift::query()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with(['vehicle', 'originalVehicle', 'station'])
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
        if ($filters->stationIds !== null && $filters->stationIds !== []) {
            $query->whereIn('station_id', $filters->stationIds);
        }

        return $query->get();
    }

    private function loadVehicles(Collection $shifts): Collection
    {
        $ids = $shifts->pluck('vehicle_id')
            ->merge($shifts->pluck('original_vehicle_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($ids)) {
            return collect();
        }

        return FleetVehicle::whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->map(fn (FleetVehicle $v) => trim($v->brand.' '.$v->model).' ('.($v->registration_number ?? '—').')');
    }

    /**
     * Vehicle row key for utilization: completed → current vehicle_id; booked → optional original.
     */
    private function utilizationVehicleBucketId(Shift $shift, bool $isBooked, bool $isCompleted, UtilizationFilters $filters): ?int
    {
        if ($isCompleted) {
            return $shift->vehicle_id ? (int) $shift->vehicle_id : null;
        }
        if ($isBooked) {
            if ($filters->attributeBookedShiftsToOriginalVehicle) {
                return (int) ($shift->original_vehicle_id ?? $shift->vehicle_id);
            }

            return $shift->vehicle_id ? (int) $shift->vehicle_id : null;
        }

        return null;
    }

    private function shiftMatchesVehicleFilter(Shift $shift, int $vehicleId, UtilizationFilters $filters): bool
    {
        if ((int) $shift->vehicle_id === $vehicleId) {
            return true;
        }
        if ($filters->attributeBookedShiftsToOriginalVehicle
            && $shift->status === ShiftStatus::Booked
            && (int) ($shift->original_vehicle_id ?? 0) === $vehicleId) {
            return true;
        }

        return false;
    }
}
