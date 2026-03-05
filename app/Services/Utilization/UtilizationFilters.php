<?php

namespace App\Services\Utilization;

/**
 * Filters for utilization queries.
 * Source of truth: shifts table (vehicle_id, station_id, starts_at, ends_at, status).
 * No separate reservations table; status = booked | completed | cancelled.
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
        public readonly ?string $timezone = null
    ) {
    }

    public function includeBooked(): bool
    {
        return $this->statusMode === self::STATUS_MODE_BOOKED || $this->statusMode === self::STATUS_MODE_BOTH;
    }

    public function includeCompleted(): bool
    {
        return $this->statusMode === self::STATUS_MODE_COMPLETED || $this->statusMode === self::STATUS_MODE_BOTH;
    }
}
