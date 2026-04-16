<?php

namespace App\Exceptions;

use RuntimeException;

class ShiftVehicleReassignmentException extends RuntimeException
{
    public static function sameVehicle(): self
    {
        return new self('Source and target vehicles must be different.');
    }

    public static function noActivePolicy(): self
    {
        return new self('No active shift policy is configured.');
    }

    public static function stationMismatch(string $detail = ''): self
    {
        return new self('Vehicles must belong to the same home station.'.($detail !== '' ? ' '.$detail : ''));
    }

    public static function targetVehicleInactive(): self
    {
        return new self('Target vehicle must be active.');
    }

    public static function shiftStationMismatch(int $shiftId): self
    {
        return new self("Shift #{$shiftId} station does not match the source vehicle's home station.");
    }

    public static function targetNotAvailableForShift(int $shiftId): self
    {
        return new self("Target vehicle is not available for shift #{$shiftId} (overlap, downtime, or daily cap).");
    }
}
