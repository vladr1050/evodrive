<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Exceptions\VehicleServiceBlockException;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleServiceBlock;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class VehicleServiceBlockService
{
    /**
     * @return list<array{starts_at: string, ends_at: string}>
     */
    public function suggestFreeSubrangesIso(int $vehicleId, Carbon $rangeStartUtc, Carbon $rangeEndUtc): array
    {
        $this->assertValidWindow($rangeStartUtc, $rangeEndUtc);

        return $this->formatSuggestions(
            $this->freeSubrangesUtc($vehicleId, $rangeStartUtc, $rangeEndUtc, null)
        );
    }

    /**
     * @throws VehicleServiceBlockException
     */
    public function create(int $vehicleId, Carbon $startsAtUtc, Carbon $endsAtUtc, ?string $note, ?int $userId): FleetVehicleServiceBlock
    {
        $this->assertValidWindow($startsAtUtc, $endsAtUtc);
        FleetVehicle::query()->findOrFail($vehicleId);

        if ($this->serviceOverlapExists($vehicleId, $startsAtUtc, $endsAtUtc, null)) {
            throw VehicleServiceBlockException::overlapsExisting();
        }

        if ($this->shiftOverlapExists($vehicleId, $startsAtUtc, $endsAtUtc)) {
            $suggestions = $this->formatSuggestions(
                $this->freeSubrangesUtc($vehicleId, $startsAtUtc, $endsAtUtc, null)
            );
            throw VehicleServiceBlockException::overlapsShifts($suggestions);
        }

        return FleetVehicleServiceBlock::query()->create([
            'fleet_vehicle_id' => $vehicleId,
            'starts_at' => $startsAtUtc->copy()->utc(),
            'ends_at' => $endsAtUtc->copy()->utc(),
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    public function cancel(FleetVehicleServiceBlock $block): void
    {
        if ($block->isCancelled()) {
            return;
        }
        $block->update(['cancelled_at' => now()->utc()]);
    }

    /**
     * Shorten the block so it ends now (UTC); vehicle becomes available immediately after.
     *
     * @throws ValidationException
     */
    public function completeEarly(FleetVehicleServiceBlock $block): void
    {
        if ($block->isCancelled()) {
            throw ValidationException::withMessages(['ends_at' => 'Cancelled blocks cannot be completed.']);
        }
        $now = now()->utc();
        if ($now->lte($block->starts_at)) {
            throw ValidationException::withMessages(['ends_at' => 'End time must be after the block start.']);
        }
        if ($now->gte($block->ends_at)) {
            return;
        }
        $block->update(['ends_at' => $now]);
    }

    /**
     * True if the vehicle has any booked shift overlapping [start, end).
     */
    public function shiftOverlapExists(int $vehicleId, Carbon $startUtc, Carbon $endUtc): bool
    {
        return Shift::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->exists();
    }

    /**
     * True if any non-cancelled service block overlaps [start, end), excluding $exceptBlockId.
     */
    public function serviceOverlapExists(int $vehicleId, Carbon $startUtc, Carbon $endUtc, ?int $exceptBlockId): bool
    {
        $q = FleetVehicleServiceBlock::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->overlappingWindow($startUtc, $endUtc);
        if ($exceptBlockId !== null) {
            $q->where('id', '!=', $exceptBlockId);
        }

        return $q->exists();
    }

    /**
     * @throws VehicleServiceBlockException
     */
    public function updateWindow(FleetVehicleServiceBlock $block, Carbon $startsAtUtc, Carbon $endsAtUtc): void
    {
        if ($block->isCancelled()) {
            throw ValidationException::withMessages(['starts_at' => 'Cannot edit a cancelled service block.']);
        }
        $this->assertValidWindow($startsAtUtc, $endsAtUtc);

        if ($this->serviceOverlapExists((int) $block->fleet_vehicle_id, $startsAtUtc, $endsAtUtc, (int) $block->id)) {
            throw VehicleServiceBlockException::overlapsExisting();
        }

        if ($this->shiftOverlapExists((int) $block->fleet_vehicle_id, $startsAtUtc, $endsAtUtc)) {
            $suggestions = $this->formatSuggestions(
                $this->freeSubrangesUtc((int) $block->fleet_vehicle_id, $startsAtUtc, $endsAtUtc, (int) $block->id)
            );
            throw VehicleServiceBlockException::overlapsShifts($suggestions);
        }

        $block->update([
            'starts_at' => $startsAtUtc->copy()->utc(),
            'ends_at' => $endsAtUtc->copy()->utc(),
        ]);
    }

    private function assertValidWindow(Carbon $startUtc, Carbon $endUtc): void
    {
        if ($startUtc->gte($endUtc)) {
            throw ValidationException::withMessages(['ends_at' => 'End must be after start.']);
        }
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function freeSubrangesUtc(int $vehicleId, Carbon $S, Carbon $E, ?int $ignoreServiceBlockId): array
    {
        $busy = $this->busyIntervalsUtc($vehicleId, $S, $E, $ignoreServiceBlockId);

        return $this->subtractMergedFromWindow($S, $E, $busy);
    }

    /**
     * Busy intervals clipped to [S,E): shifts + other service blocks.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function busyIntervalsUtc(int $vehicleId, Carbon $S, Carbon $E, ?int $ignoreServiceBlockId): array
    {
        $intervals = [];

        $shifts = Shift::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', ShiftStatus::Booked)
            ->where('starts_at', '<', $E)
            ->where('ends_at', '>', $S)
            ->get(['starts_at', 'ends_at']);
        foreach ($shifts as $s) {
            $a = $s->starts_at->copy()->max($S);
            $b = $s->ends_at->copy()->min($E);
            if ($a->lt($b)) {
                $intervals[] = [$a, $b];
            }
        }

        $blocks = FleetVehicleServiceBlock::query()
            ->where('fleet_vehicle_id', $vehicleId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $E)
            ->where('ends_at', '>', $S);
        if ($ignoreServiceBlockId !== null) {
            $blocks->where('id', '!=', $ignoreServiceBlockId);
        }
        foreach ($blocks->get(['starts_at', 'ends_at']) as $b) {
            $a = $b->starts_at->copy()->max($S);
            $z = $b->ends_at->copy()->min($E);
            if ($a->lt($z)) {
                $intervals[] = [$a, $z];
            }
        }

        return $this->mergeIntervals($intervals);
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon}>  $mergedBusy
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function subtractMergedFromWindow(Carbon $S, Carbon $E, array $mergedBusy): array
    {
        if ($mergedBusy === []) {
            return [[$S->copy(), $E->copy()]];
        }

        $free = [];
        $cursor = $S->copy();
        foreach ($mergedBusy as [$a, $b]) {
            if ($b->lte($cursor)) {
                continue;
            }
            if ($a->gte($E)) {
                break;
            }
            if ($cursor->lt($a)) {
                $free[] = [$cursor->copy(), $a->copy()->min($E)];
            }
            if ($b->gt($cursor)) {
                $cursor = $b->copy();
            }
            if ($cursor->gte($E)) {
                break;
            }
        }
        if ($cursor->lt($E)) {
            $free[] = [$cursor->copy(), $E->copy()];
        }

        return array_values(array_filter($free, fn ($p) => $p[0]->lt($p[1])));
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon}>  $intervals
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        usort($intervals, fn ($x, $y) => $x[0]->lt($y[0]) ? -1 : ($x[0]->gt($y[0]) ? 1 : 0));
        $out = [];
        foreach ($intervals as [$a, $b]) {
            if (empty($out)) {
                $out[] = [$a->copy(), $b->copy()];

                continue;
            }
            /** @var array{0: Carbon, 1: Carbon} $last */
            $last = $out[count($out) - 1];
            if ($a->lte($last[1])) {
                $out[count($out) - 1][1] = $last[1]->max($b);
            } else {
                $out[] = [$a->copy(), $b->copy()];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon}>  $pairs
     * @return list<array{starts_at: string, ends_at: string}>
     */
    private function formatSuggestions(array $pairs): array
    {
        $out = [];
        foreach ($pairs as [$a, $b]) {
            $out[] = [
                'starts_at' => $a->copy()->utc()->toIso8601String(),
                'ends_at' => $b->copy()->utc()->toIso8601String(),
            ];
        }

        return $out;
    }

    public static function policyTimezone(): string
    {
        return ShiftPolicy::active()?->timezone ?? 'Europe/Riga';
    }
}
