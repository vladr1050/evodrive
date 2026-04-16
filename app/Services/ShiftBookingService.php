<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftEvent;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftBookingService
{
    public function __construct(
        protected ShiftAvailabilityService $availabilityService
    ) {}

    /**
     * @throws ShiftBookingException
     */
    public function bookShift(int $driverId, int $stationId, Carbon $startsAt, float $durationHours): Shift
    {
        $policy = ShiftPolicy::active();
        if (! $policy) {
            throw ShiftBookingException::noVehiclesAvailable();
        }

        $this->validatePolicy($policy, $startsAt, $durationHours);
        $this->validateDriverDailyLimit($driverId, $startsAt, $policy);

        return DB::transaction(function () use ($driverId, $stationId, $startsAt, $durationHours, $policy) {
            $endsAt = $startsAt->copy()->addMinutes((int) round($durationHours * 60));
            $startsAtUtc = $startsAt->copy()->utc();
            $endsAtUtc = $endsAt->copy()->utc();
            $vehicleId = $this->selectAvailableVehicleUnderLock($stationId, $startsAtUtc, $endsAtUtc, $policy);
            if (! $vehicleId) {
                throw ShiftBookingException::noVehiclesAvailable();
            }

            $vehicle = FleetVehicle::findOrFail($vehicleId);
            if ((int) $vehicle->home_station_id !== (int) $stationId) {
                throw ShiftBookingException::stationMismatch();
            }

            $shift = Shift::create([
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
                'original_vehicle_id' => $vehicleId,
                'station_id' => $stationId,
                'starts_at' => $startsAtUtc,
                'ends_at' => $endsAtUtc,
                'status' => ShiftStatus::Booked,
            ]);

            ShiftEvent::logCreated($shift, 'driver', $driverId);

            return $shift->load('vehicle', 'station');
        });
    }

    /**
     * Select one available vehicle under row lock. Returns vehicle id or null.
     * Uses lockForUpdate() so concurrent bookings block; recheck via vehicleAvailableFor()
     * inside the same transaction ensures no double-booking under race conditions.
     */
    protected function selectAvailableVehicleUnderLock(int $stationId, Carbon $startsAt, Carbon $endsAt, ShiftPolicy $policy): ?int
    {
        $vehicleIds = FleetVehicle::where('home_station_id', $stationId)
            ->where('status', \App\Enums\VehicleStatus::Active)
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            return null;
        }

        $locked = FleetVehicle::whereIn('id', $vehicleIds->all())->lockForUpdate()->get();
        foreach ($locked as $vehicle) {
            if ($this->availabilityService->vehicleAvailableFor($vehicle->id, $startsAt, $endsAt, $policy)) {
                return $vehicle->id;
            }
        }

        return null;
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

    /**
     * @throws ShiftBookingException
     */
    protected function validateDriverDailyLimit(int $driverId, Carbon $startsAt, ShiftPolicy $policy): void
    {
        $max = $policy->max_shifts_per_driver_per_day;
        if ($max === null) {
            return;
        }
        $dayStart = $startsAt->copy()->startOfDay();
        $dayEnd = $startsAt->copy()->endOfDay();
        $count = Shift::where('driver_id', $driverId)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '>=', $dayStart)
            ->where('starts_at', '<=', $dayEnd)
            ->count();
        if ($count >= $max) {
            throw ShiftBookingException::dailyLimitExceeded();
        }
    }
}
