<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Models\FleetVehicle;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Stats: per-day, per-vehicle hours in status Booked/Completed (out of 24h).
 * Shift 20:00–02:00 gives 4h to the first day and 2h to the next.
 */
class VehicleUtilizationStatsService
{
    /**
     * @param  string|null  $tz  Timezone for day boundaries
     * @return array<int, array{date: string, vehicle_id: int, vehicle_label: string, hours: float}>
     */
    public function getDailyUtilization(?string $tz = 'Europe/Riga'): array
    {
        $tz = $tz ?: 'Europe/Riga';

        $shifts = Shift::query()
            ->whereIn('status', [ShiftStatus::Booked, ShiftStatus::Completed])
            ->with('vehicle')
            ->orderBy('starts_at')
            ->get();

        if ($shifts->isEmpty()) {
            return [];
        }

        $firstStart = $shifts->min(fn (Shift $s) => $s->starts_at);
        $lastEnd = $shifts->max(fn (Shift $s) => $s->ends_at);
        $startDate = $firstStart->copy()->setTimezone($tz)->startOfDay();
        $endDate = $lastEnd->copy()->setTimezone($tz)->startOfDay();

        $vehicleLabels = $this->vehicleLabels($shifts);

        // [ (dateKey => [ vehicle_id => hours ]) ]
        $byDateVehicle = [];

        foreach ($shifts as $shift) {
            if (! $shift->vehicle_id) {
                continue;
            }
            $shiftStart = $shift->starts_at->copy()->setTimezone($tz);
            $shiftEnd = $shift->ends_at->copy()->setTimezone($tz);
            $day = $shiftStart->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            while ($day->lte($shiftEnd)) {
                $dayStart = $day->copy()->startOfDay();
                $dayEndThis = $day->copy()->endOfDay();
                $overlapStart = $shiftStart->copy()->max($dayStart);
                $overlapEnd = $shiftEnd->copy()->min($dayEndThis);
                if ($overlapStart->lt($overlapEnd)) {
                    $minutes = (int) round($overlapStart->diffInMinutes($overlapEnd));
                    $hours = round($minutes / 60, 2);
                    $dateKey = $day->format('Y-m-d');
                    if (! isset($byDateVehicle[$dateKey][$shift->vehicle_id])) {
                        $byDateVehicle[$dateKey][$shift->vehicle_id] = 0.0;
                    }
                    $byDateVehicle[$dateKey][$shift->vehicle_id] += $hours;
                }
                $day->addDay();
            }
        }

        $rows = [];
        foreach ($byDateVehicle as $dateKey => $vehicleHours) {
            foreach ($vehicleHours as $vehicleId => $hours) {
                $rows[] = [
                    'date' => $dateKey,
                    'vehicle_id' => $vehicleId,
                    'vehicle_label' => $vehicleLabels->get($vehicleId, '—'),
                    'hours' => round($hours, 2),
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$b['date'], $a['vehicle_label']] <=> [$a['date'], $b['vehicle_label']]);

        return $rows;
    }

    private function vehicleLabels(Collection $shifts): Collection
    {
        $ids = $shifts->pluck('vehicle_id')->filter()->unique()->values()->all();
        if (empty($ids)) {
            return collect();
        }
        return FleetVehicle::whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->map(fn (FleetVehicle $v) => trim($v->brand . ' ' . $v->model) . ' (' . ($v->registration_number ?? '—') . ')');
    }
}
