<?php

namespace App\Services\Utilization;

/**
 * Filters for utilization queries.
 * Source of truth: shifts table (vehicle_id, original_vehicle_id, station_id, starts_at, ends_at, status).
 * When attributeBookedShiftsToOriginalVehicle is true, booked planned minutes are attributed to
 * original_vehicle_id (first assigned truck) instead of the current vehicle_id after reassignment.
 * Completed minutes always use vehicle_id (actual truck).
 */
final class UtilizationFilters
{
    public const STATUS_MODE_COMPLETED = 'completed';

    public const STATUS_MODE_BOOKED = 'booked';

    public const STATUS_MODE_BOTH = 'both';

    public function __construct(
        public readonly ?array $vehicleIds = null,
        public readonly ?array $stationIds = null,
        public readonly string $statusMode = self::STATUS_MODE_BOTH,
        public readonly ?string $timezone = null,
        public readonly bool $attributeBookedShiftsToOriginalVehicle = false
    ) {}

    public function includeBooked(): bool
    {
        return $this->statusMode === self::STATUS_MODE_BOOKED || $this->statusMode === self::STATUS_MODE_BOTH;
    }

    public function includeCompleted(): bool
    {
        return $this->statusMode === self::STATUS_MODE_COMPLETED || $this->statusMode === self::STATUS_MODE_BOTH;
    }
}
