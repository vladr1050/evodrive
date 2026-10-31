<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriverWorkedShiftsReportService
{
    /**
     * Build a per-driver worked-shifts report for the given local date range.
     *
     * @return array{
     *     driver_id: int,
     *     driver_name: string,
     *     driver_email: ?string,
     *     driver_phone: ?string,
     *     date_from: string,
     *     date_to: string,
     *     timezone: string,
     *     include_booked: bool,
     *     generated_at: string,
     *     shifts: array<int, array{
     *         shift_id: int,
     *         date: string,
     *         start: string,
     *         end: string,
     *         hours: float,
     *         station: string,
     *         vehicle: string,
     *         status: string,
     *         started_via_bot_at: ?string
     *     }>,
     *     shift_count: int,
     *     total_hours: float
     * }
     */
    public function build(Driver $driver, string $dateFrom, string $dateTo, bool $includeBooked = false): array
    {
        $tz = ShiftPolicy::active()?->timezone ?: 'Europe/Riga';
        $from = Carbon::parse($dateFrom, $tz)->startOfDay();
        $to = Carbon::parse($dateTo, $tz)->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            [$dateFrom, $dateTo] = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }

        $statuses = $includeBooked
            ? [ShiftStatus::Completed, ShiftStatus::Booked]
            : [ShiftStatus::Completed];

        /** @var Collection<int, Shift> $shifts */
        $shifts = Shift::query()
            ->with(['station', 'vehicle', 'originalVehicle'])
            ->where('driver_id', $driver->id)
            ->whereIn('status', $statuses)
            ->where('starts_at', '<', $to->copy()->utc())
            ->where('ends_at', '>', $from->copy()->utc())
            ->orderBy('starts_at')
            ->get();

        $rows = [];
        $totalMinutes = 0;
        foreach ($shifts as $shift) {
            $startLocal = $shift->starts_at->copy()->setTimezone($tz);
            $endLocal = $shift->ends_at->copy()->setTimezone($tz);
            $minutes = (int) round($startLocal->diffInMinutes($endLocal));
            $totalMinutes += $minutes;
            $rows[] = [
                'shift_id' => (int) $shift->id,
                'date' => $startLocal->format('Y-m-d'),
                'start' => $startLocal->format('H:i'),
                'end' => $endLocal->format('H:i'),
                'hours' => round($minutes / 60, 2),
                'station' => $shift->station?->name ?? '—',
                'vehicle' => $this->vehicleLabel($shift),
                'status' => $shift->status->value,
                'started_via_bot_at' => $shift->started_via_bot_at
                    ? $shift->started_via_bot_at->copy()->setTimezone($tz)->format('Y-m-d H:i')
                    : null,
            ];
        }

        return [
            'driver_id' => (int) $driver->id,
            'driver_name' => $driver->name,
            'driver_email' => $driver->email,
            'driver_phone' => $driver->phone,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'timezone' => $tz,
            'include_booked' => $includeBooked,
            'generated_at' => now($tz)->format('Y-m-d H:i'),
            'shifts' => $rows,
            'shift_count' => count($rows),
            'total_hours' => round($totalMinutes / 60, 2),
        ];
    }

    protected function vehicleLabel(Shift $shift): string
    {
        $v = $shift->vehicle;
        $current = $v
            ? trim(($v->registration_number ?: '') ?: ($v->label ?? ('#'.$v->id)))
            : '—';
        if ($shift->original_vehicle_id
            && (int) $shift->original_vehicle_id !== (int) $shift->vehicle_id
            && $shift->originalVehicle
        ) {
            $orig = $shift->originalVehicle;
            $origLabel = trim(($orig->registration_number ?: '') ?: ($orig->label ?? ('#'.$orig->id)));

            return $current.' (was '.$origLabel.')';
        }

        return $current !== '' ? $current : '—';
    }
}
