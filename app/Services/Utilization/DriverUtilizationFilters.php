<?php

namespace App\Services\Utilization;

/**
 * Filters for driver utilization queries (shifts by driver_id, station_id, vehicle_id, status).
 */
final class DriverUtilizationFilters
{
    public const STATUS_MODE_COMPLETED = 'completed';

    public const STATUS_MODE_BOOKED = 'booked';

    public const STATUS_MODE_BOTH = 'both';

    public function __construct(
        public readonly ?array $driverIds = null,
        public readonly ?array $stationIds = null,
        public readonly ?array $vehicleIds = null,
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
