<?php

namespace App\Exceptions;

use Exception;

class ShiftBookingException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $reasonCode,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function noVehiclesAvailable(): self
    {
        return new self('No vehicles available for this time slot.', 'NO_VEHICLES');
    }

    public static function overlap(): self
    {
        return new self('Shift overlaps with an existing shift.', 'OVERLAP');
    }

    public static function driverShiftOverlap(): self
    {
        return new self('This time overlaps another of your booked shifts.', 'DRIVER_SHIFT_OVERLAP');
    }

    public static function downtimeViolation(): self
    {
        return new self('Vehicle downtime between shifts not respected.', 'DOWNTIME');
    }

    public static function dailyLimitExceeded(): self
    {
        return new self('Maximum shifts per day for driver exceeded.', 'DAILY_LIMIT');
    }

    public static function vehicle24hExceeded(): self
    {
        return new self('Vehicle would exceed 24h in a day.', 'VEHICLE_24H');
    }

    public static function invalidDuration(): self
    {
        return new self('Duration not allowed by policy.', 'INVALID_DURATION');
    }

    public static function invalidStartTime(): self
    {
        return new self('Start time must align to policy time slot.', 'INVALID_START');
    }

    public static function stationMismatch(): self
    {
        return new self('Station does not match vehicle home station.', 'STATION_MISMATCH');
    }

    public static function shiftNotEditable(): self
    {
        return new self('This shift cannot be edited (not enough gap before or after on this vehicle).', 'NOT_EDITABLE');
    }
}
