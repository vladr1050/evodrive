<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance-oriented tests for checkAvailability: no N+1, bounded query count.
 */
class ShiftAvailabilityPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;
    protected Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 15,
            'timezone' => 'UTC',
        ]);
        $this->station = Station::factory()->create();
    }

    /**
     * 50 vehicles at station, 500 shifts across the week. checkAvailability must use
     * a bounded number of queries (no per-vehicle N+1). Assert query count instead of
     * strict timing to avoid flakiness in CI.
     */
    public function test_check_availability_no_n_plus_one_with_many_vehicles_and_shifts(): void
    {
        $vehicles = FleetVehicle::factory()->count(50)->create([
            'home_station_id' => $this->station->id,
            'status' => \App\Enums\VehicleStatus::Active,
        ]);
        $drivers = Driver::factory()->count(20)->create();

        $weekStart = Carbon::tomorrow()->startOfWeek();
        for ($i = 0; $i < 500; $i++) {
            $day = $weekStart->copy()->addDays($i % 7);
            $hour = 6 + ($i % 12);
            $vehicle = $vehicles->random();
            $driver = $drivers->random();
            Shift::factory()->create([
                'vehicle_id' => $vehicle->id,
                'station_id' => $this->station->id,
                'driver_id' => $driver->id,
                'starts_at' => $day->copy()->setTime($hour, 0),
                'ends_at' => $day->copy()->setTime($hour + 4, 0),
                'status' => ShiftStatus::Booked,
            ]);
        }

        $startsAt = $weekStart->copy()->addDays(3)->setTime(14, 0);
        $service = app(ShiftAvailabilityService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $service->checkAvailability($this->station->id, $startsAt, 4);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        DB::disableQueryLog();

        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('vehicle_ids', $result);
        $this->assertIsArray($result['vehicle_ids']);

        // No N+1: we expect at most a small constant (policy + vehicles + one prefetch of shifts).
        // With N+1 we would see 1 + 50 * 4 = 201+ queries. Cap at 15 to be safe for any extra meta queries.
        $this->assertLessThanOrEqual(
            15,
            $queryCount,
            'checkAvailability must not do per-vehicle N+1 queries. Got ' . $queryCount . ' queries for 50 vehicles.'
        );
    }
}
