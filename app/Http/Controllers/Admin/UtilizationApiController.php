<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\UtilizationFilters;
use App\Services\Utilization\VehicleUtilizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Raw JSON API for fleet utilization (admin-only).
 * Used for integrations; does not depend on Filament.
 */
class UtilizationApiController extends Controller
{
    public function __construct(
        private VehicleUtilizationService $service
    ) {}

    /**
     * GET /api/admin/utilization/daily
     * Query: date_from, date_to, vehicle_ids[] (optional), station_ids[] (optional), status_mode=completed|booked|both
     */
    public function daily(Request $request): JsonResponse
    {
        $this->authorizeFleetManagement();
        $valid = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'station_ids' => 'nullable|array',
            'station_ids.*' => 'integer',
            'status_mode' => 'nullable|in:completed,booked,both',
            'attribute_booked_to_original_vehicle' => 'nullable|boolean',
        ]);
        $range = new DateRange($valid['date_from'], $valid['date_to']);
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $filters = new UtilizationFilters(
            ! empty($valid['vehicle_ids']) ? $valid['vehicle_ids'] : null,
            ! empty($valid['station_ids']) ? $valid['station_ids'] : null,
            $valid['status_mode'] ?? UtilizationFilters::STATUS_MODE_BOTH,
            $tz,
            (bool) ($valid['attribute_booked_to_original_vehicle'] ?? false)
        );
        $rows = $this->service->getDailyUtilization($range, $filters);
        $data = $rows->map(fn ($r) => [
            'date' => $r->date,
            'vehicle_id' => $r->vehicle_id,
            'vehicle_name' => $r->vehicle_name,
            'booked_hours' => round($r->booked_minutes / 60, 2),
            'completed_hours' => round($r->completed_minutes / 60, 2),
            'total_hours' => $r->total_hours,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/admin/utilization/daily/{vehicleId}/{date}
     * Returns intervals breakdown for one vehicle on one day.
     */
    public function dailyIntervals(Request $request, int $vehicleId, string $date): JsonResponse
    {
        $this->authorizeFleetManagement();
        $request->validate(['date' => 'sometimes|date']); // date already in path
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format (use Y-m-d)'], 422);
        }
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $attrOriginal = $request->boolean('attribute_booked_to_original_vehicle');
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOTH, $tz, $attrOriginal);
        $intervals = $this->service->getDailyIntervals($vehicleId, $date, $filters);

        return response()->json(['data' => $intervals]);
    }

    /**
     * GET /api/admin/utilization/sources
     * Query: date_from, date_to, vehicle_ids[] (optional), station_ids[] (optional), status_mode
     * Returns raw shifts in range (for debugging).
     */
    public function sources(Request $request): JsonResponse
    {
        $this->authorizeFleetManagement();
        $valid = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'station_ids' => 'nullable|array',
            'station_ids.*' => 'integer',
            'status_mode' => 'nullable|in:completed,booked,both',
            'attribute_booked_to_original_vehicle' => 'nullable|boolean',
        ]);
        $range = new DateRange($valid['date_from'], $valid['date_to']);
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $filters = new UtilizationFilters(
            ! empty($valid['vehicle_ids']) ? $valid['vehicle_ids'] : null,
            ! empty($valid['station_ids']) ? $valid['station_ids'] : null,
            $valid['status_mode'] ?? UtilizationFilters::STATUS_MODE_BOTH,
            $tz,
            (bool) ($valid['attribute_booked_to_original_vehicle'] ?? false)
        );
        $shifts = $this->service->getSourcesInRange($range, $filters);
        $data = $shifts->map(fn (Shift $s) => [
            'id' => $s->id,
            'vehicle_id' => $s->vehicle_id,
            'original_vehicle_id' => $s->original_vehicle_id,
            'station_id' => $s->station_id,
            'starts_at' => $s->starts_at->toIso8601String(),
            'ends_at' => $s->ends_at->toIso8601String(),
            'status' => $s->status->value,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    private function authorizeFleetManagement(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canAccessResource('statistics')) {
            abort(403, 'Access denied.');
        }
    }
}
