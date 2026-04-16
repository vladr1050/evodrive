<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\ShiftVehicleReassignmentException;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\ShiftVehicleReplacement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShiftVehicleReassignmentService
{
    public function __construct(
        protected ShiftAvailabilityService $availabilityService
    ) {}

    /**
     * Move future booked shifts from one fleet vehicle to another (same home station).
     * Only shifts with starts_at >= effectiveFrom (interpreted in policy timezone) are updated.
     * original_vehicle_id is never changed — audit rows are written to shift_vehicle_replacements.
     *
     * @return array{updated: int, batch_id: string}
     */
    public function reassignFutureBookedShifts(
        int $fromVehicleId,
        int $toVehicleId,
        Carbon $effectiveFromInPolicyTimezone,
        ?int $actingUserId = null,
        ?string $note = null
    ): array {
        if ($fromVehicleId === $toVehicleId) {
            throw ShiftVehicleReassignmentException::sameVehicle();
        }

        $policy = ShiftPolicy::active();
        if (! $policy) {
            throw ShiftVehicleReassignmentException::noActivePolicy();
        }

        $tz = $policy->timezone ?? 'Europe/Riga';
        $effectiveFromUtc = $effectiveFromInPolicyTimezone->copy()->timezone($tz)->utc();

        $fromVehicle = FleetVehicle::query()->findOrFail($fromVehicleId);
        $toVehicle = FleetVehicle::query()->findOrFail($toVehicleId);

        if ((int) $fromVehicle->home_station_id !== (int) $toVehicle->home_station_id) {
            throw ShiftVehicleReassignmentException::stationMismatch();
        }

        if ($toVehicle->status !== VehicleStatus::Active) {
            throw ShiftVehicleReassignmentException::targetVehicleInactive();
        }

        $batchId = (string) Str::uuid();

        return DB::transaction(function () use (
            $fromVehicleId,
            $toVehicleId,
            $effectiveFromUtc,
            $fromVehicle,
            $policy,
            $batchId,
            $actingUserId,
            $note
        ): array {
            $shifts = Shift::query()
                ->where('vehicle_id', $fromVehicleId)
                ->where('status', ShiftStatus::Booked)
                ->where('starts_at', '>=', $effectiveFromUtc)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($shifts->isEmpty()) {
                return ['updated' => 0, 'batch_id' => $batchId];
            }

            foreach ($shifts as $shift) {
                if ((int) $shift->station_id !== (int) $fromVehicle->home_station_id) {
                    throw ShiftVehicleReassignmentException::shiftStationMismatch((int) $shift->id);
                }

                if (! $this->availabilityService->vehicleAvailableForExcludingShift(
                    $toVehicleId,
                    (int) $shift->id,
                    $shift->starts_at,
                    $shift->ends_at,
                    $policy
                )) {
                    throw ShiftVehicleReassignmentException::targetNotAvailableForShift((int) $shift->id);
                }
            }

            $updated = 0;
            foreach ($shifts as $shift) {
                ShiftVehicleReplacement::query()->create([
                    'batch_id' => $batchId,
                    'shift_id' => $shift->id,
                    'from_vehicle_id' => $fromVehicleId,
                    'to_vehicle_id' => $toVehicleId,
                    'created_by_user_id' => $actingUserId,
                    'note' => $note,
                ]);

                $shift->vehicle_id = $toVehicleId;
                $shift->save();
                $updated++;
            }

            return ['updated' => $updated, 'batch_id' => $batchId];
        });
    }
}
