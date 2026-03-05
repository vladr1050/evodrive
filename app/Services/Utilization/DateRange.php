<?php

namespace App\Services\Utilization;

use Carbon\Carbon;

/**
 * Immutable date range (inclusive start and end dates, local date only).
 */
final class DateRange
{
    public function __construct(
        public readonly string $dateFrom,
        public readonly string $dateTo
    ) {
    }

    public static function fromStrings(string $from, string $to): self
    {
        return new self($from, $to);
    }

    public function days(): int
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        return (int) $from->diffInDays($to) + 1;
    }

    /**
     * @return array<string> List of Y-m-d dates in range
     */
    public function dateKeys(): array
    {
        $keys = [];
        $current = Carbon::parse($this->dateFrom)->startOfDay();
        $end = Carbon::parse($this->dateTo)->startOfDay();
        while ($current->lte($end)) {
            $keys[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $keys;
    }
}
