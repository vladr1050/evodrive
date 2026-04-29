<?php

namespace App\Exceptions;

use Exception;

class VehicleServiceBlockException extends Exception
{
    /**
     * @param  list<array{starts_at: string, ends_at: string}>  $suggestions
     */
    public function __construct(
        string $message,
        public readonly string $reasonCode,
        public readonly array $suggestions = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function overlapsExisting(): self
    {
        return new self('This range overlaps another scheduled service for this vehicle.', 'OVERLAPS_SERVICE', []);
    }

    /**
     * @param  list<array{starts_at: string, ends_at: string}>  $suggestions
     */
    public static function overlapsShifts(array $suggestions): self
    {
        return new self(
            'This range includes booked shifts. Choose a sub-range that does not overlap shifts.',
            'OVERLAPS_SHIFTS',
            $suggestions
        );
    }
}
