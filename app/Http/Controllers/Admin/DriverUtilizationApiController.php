<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShiftPolicy;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\DriverUtilizationFilters;
use App\Services\Utilization\DriverUtilizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Raw JSON API for driver utilization (admin-only).
 */
class DriverUtilizationApiController extends Controller
{
    public function __construct(
        private DriverUtilizationService $service
    ) {}

    /**
     * GET /api/admin/driver-utilization
     * Query: date_from, date_to, driver_ids[], station_ids[], vehicle_ids[], status_mode
     */
    public function daily(Request $request): JsonResponse
    {
        $this->authorizeFleetManagement();
        $valid = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'driver_ids' => 'nullable|array',
            'driver_ids.*' => 'integer',
            'station_ids' => 'nullable|array',
            'station_ids.*' => 'integer',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'status_mode' => 'nullable|in:completed,booked,both',
            'attribute_booked_to_original_vehicle' => 'nullable|boolean',
        ]);
        $range = new DateRange($valid['date_from'], $valid['date_to']);
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $filters = new DriverUtilizationFilters(
            ! empty($valid['driver_ids']) ? $valid['driver_ids'] : null,
            ! empty($valid['station_ids']) ? $valid['station_ids'] : null,
            ! empty($valid['vehicle_ids']) ? $valid['vehicle_ids'] : null,
            $valid['status_mode'] ?? DriverUtilizationFilters::STATUS_MODE_BOTH,
            $tz,
            (bool) ($valid['attribute_booked_to_original_vehicle'] ?? false)
        );
        $rows = $this->service->getDailyDriverUtilization($range, $filters);
        $data = $rows->map(fn ($r) => [
            'date' => $r->date,
            'driver_id' => $r->driver_id,
            'driver_name' => $r->driver_name,
            'planned_hours' => round($r->planned_minutes / 60, 2),
            'worked_hours' => round($r->worked_minutes / 60, 2),
            'total_hours' => $r->total_hours,
            'stations' => $r->stations ?? [],
            'vehicles' => $r->vehicles ?? [],
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/admin/driver-utilization/{driverId}/{date}
     * Returns shifts breakdown for one driver on one day.
     */
    public function dayBreakdown(Request $request, int $driverId, string $date): JsonResponse
    {
        $this->authorizeFleetManagement();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Invalid date format (use Y-m-d)'], 422);
        }
        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone', 'Europe/Riga');
        $attrOriginal = $request->boolean('attribute_booked_to_original_vehicle');
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOTH, $tz, $attrOriginal);
        $breakdown = $this->service->getDriverDayBreakdown($driverId, $date, $filters);

        return response()->json(['data' => $breakdown]);
    }

    private function authorizeFleetManagement(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canAccessResource('fleet_management')) {
            abort(403, 'Access denied.');
        }
    }
}
