<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\Driver;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftCopyService
{
    public function __construct(
        protected ShiftAvailabilityService $availabilityService,
        protected ShiftBookingService $bookingService
    ) {}

    /**
     * Preview copy: no DB writes. Returns proposed (available) and conflicts.
     *
     * @return array{proposed: array<int, array{date: string, start_time: string, duration_hours: int, station_id: int, available_vehicle_count: int}>, conflicts: array<int, array{date: string, start_time: string, duration_hours: int, reason_code: string}>}
     */
    public function previewCopyWeek(Driver $driver, Carbon $targetWeekStart): array
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            return ['proposed' => [], 'conflicts' => []];
        }

        $tz = $policy->timezone ?? 'UTC';
        $targetWeekStartTz = Carbon::create(
            $targetWeekStart->year,
            $targetWeekStart->month,
            $targetWeekStart->day,
            0,
            0,
            0,
            $tz
        )->startOfWeek(Carbon::MONDAY);
        $prevWeekStart = $targetWeekStartTz->copy()->subWeek();
        $prevWeekEnd = $prevWeekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $shifts = Shift::where('driver_id', $driver->id)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '>=', $prevWeekStart)
            ->where('starts_at', '<=', $prevWeekEnd)
            ->with('station')
            ->orderBy('starts_at')
            ->get();

        $proposed = [];
        $conflicts = [];
        /** @var list<array{start: Carbon, end: Carbon}> */
        $plannedCopyUtcWindows = [];

        $planningMin = now($tz)->startOfDay();
        $planningMax = now($tz)->copy()->addDays($policy->planning_window_days)->endOfDay();

        foreach ($shifts as $shift) {
            $durationHours = (int) round($shift->durationHours());
            $shiftStartsInTz = Carbon::parse($shift->getRawOriginal('starts_at'), 'UTC')->setTimezone($tz);
            $daysToAdd = $shiftStartsInTz->dayOfWeek === 0 ? 6 : $shiftStartsInTz->dayOfWeek - 1;
            $newStartsAt = $targetWeekStartTz->copy()
                ->addDays($daysToAdd)
                ->setHour($shiftStartsInTz->hour)
                ->setMinute($shiftStartsInTz->minute)
                ->setSecond($shiftStartsInTz->second);
            $newStartsAtForOutput = $newStartsAt->copy()->setTimezone($tz);
            $newStartsAtInTz = $newStartsAt->copy()->setTimezone($tz);

            if ($newStartsAtInTz->lt($planningMin) || $newStartsAtInTz->gt($planningMax)) {
                $conflicts[] = [
                    'date' => $newStartsAtForOutput->format('Y-m-d'),
                    'start_time' => $newStartsAtForOutput->format('H:i'),
                    'duration_hours' => $durationHours,
                    'reason_code' => 'OUTSIDE_PLANNING_WINDOW',
                ];

                continue;
            }

            try {
                $startsAtUtc = $newStartsAt->copy()->setTimezone('UTC');
                $result = $this->availabilityService->checkAvailability(
                    $shift->station_id,
                    $startsAtUtc,
                    (float) $durationHours
                );
            } catch (ShiftBookingException $e) {
                $conflicts[] = [
                    'date' => $newStartsAtForOutput->format('Y-m-d'),
                    'start_time' => $newStartsAtForOutput->format('H:i'),
                    'duration_hours' => $durationHours,
                    'reason_code' => $e->reasonCode,
                ];

                continue;
            }

            if (($result['count'] ?? 0) <= 0) {
                $conflicts[] = [
                    'date' => $newStartsAtForOutput->format('Y-m-d'),
                    'start_time' => $newStartsAtForOutput->format('H:i'),
                    'duration_hours' => $durationHours,
                    'reason_code' => 'NO_VEHICLES',
                ];

                continue;
            }

            $endsAtUtc = $startsAtUtc->copy()->addMinutes((int) round($durationHours * 60));
            foreach ($plannedCopyUtcWindows as $w) {
                if ($startsAtUtc->lt($w['end']) && $endsAtUtc->gt($w['start'])) {
                    $conflicts[] = [
                        'date' => $newStartsAtForOutput->format('Y-m-d'),
                        'start_time' => $newStartsAtForOutput->format('H:i'),
                        'duration_hours' => $durationHours,
                        'reason_code' => 'DRIVER_SHIFT_OVERLAP',
                    ];

                    continue 2;
                }
            }

            if (Shift::driverHasOverlappingBookedShift((int) $driver->id, $startsAtUtc, $endsAtUtc)) {
                $conflicts[] = [
                    'date' => $newStartsAtForOutput->format('Y-m-d'),
                    'start_time' => $newStartsAtForOutput->format('H:i'),
                    'duration_hours' => $durationHours,
                    'reason_code' => 'DRIVER_SHIFT_OVERLAP',
                ];

                continue;
            }

            $proposed[] = [
                'date' => $newStartsAtForOutput->format('Y-m-d'),
                'start_time' => $newStartsAtForOutput->format('H:i'),
                'duration_hours' => $durationHours,
                'station_id' => $shift->station_id,
                'available_vehicle_count' => $result['count'],
            ];
            $plannedCopyUtcWindows[] = [
                'start' => $startsAtUtc->copy(),
                'end' => $endsAtUtc->copy(),
            ];
        }

        return ['proposed' => $proposed, 'conflicts' => $conflicts];
    }

    /**
     * Confirm copy: book each selection in one transaction. All-or-nothing.
     *
     * @param  array<int, array{station_id: int, starts_at: string, duration_hours: int|float}>  $selections
     * @return array{success: bool, shifts?: array, conflicts?: array}
     */
    public function confirmCopyWeek(Driver $driver, array $selections): array
    {
        if (empty($selections)) {
            return ['success' => true, 'shifts' => []];
        }

        $created = [];
        $lastSelection = null;
        try {
            DB::transaction(function () use ($driver, $selections, &$created, &$lastSelection) {
                foreach ($selections as $one) {
                    $lastSelection = $one;
                    $stationId = (int) $one['station_id'];
                    $startsAt = Carbon::parse($one['starts_at']);
                    $durationHours = (float) $one['duration_hours'];
                    $shift = $this->bookingService->bookShift($driver->id, $stationId, $startsAt, $durationHours);
                    $created[] = $shift;
                }
            });
        } catch (ShiftBookingException $e) {
            $sel = $lastSelection ?? $selections[array_key_first($selections)] ?? [];
            $startsAt = isset($sel['starts_at']) ? Carbon::parse($sel['starts_at']) : null;

            return [
                'success' => false,
                'conflicts' => [
                    [
                        'date' => $startsAt?->format('Y-m-d'),
                        'start_time' => $startsAt?->format('H:i'),
                        'duration_hours' => (int) round((float) ($sel['duration_hours'] ?? 0)),
                        'reason_code' => $e->reasonCode,
                    ],
                ],
            ];
        }

        return [
            'success' => true,
            'shifts' => array_map(fn ($s) => [
                'id' => $s->id,
                'starts_at' => $s->starts_at->toIso8601String(),
                'ends_at' => $s->ends_at->toIso8601String(),
                'station_id' => $s->station_id,
            ], $created),
        ];
    }
}
