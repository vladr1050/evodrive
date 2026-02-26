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
use Tests\TestCase;

class ShiftVehicle24hCapTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;
    protected Station $station;
    protected FleetVehicle $vehicle;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'min_duration_hours' => 2,
            'allowed_durations_json' => [2, 4, 6, 8, 10, 12, 20],
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'time_slot_minutes' => 15,
            'timezone' => 'UTC',
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver = Driver::factory()->create();
    }

    /**
     * Shifts totalling exactly 24h on one day => any extra slot that day returns count=0.
     */
    public function test_vehicle_cap_exact_24h_blocks_any_additional(): void
    {
        $day = Carbon::create(2026, 3, 15, 0, 0, 0, 'UTC');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(0, 0),
            'ends_at' => $day->copy()->setTime(8, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(8, 0),
            'ends_at' => $day->copy()->setTime(16, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(16, 0),
            'ends_at' => $day->copy()->setTime(24, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $service = app(ShiftAvailabilityService::class);
        $availability = $service->checkAvailability(
            $this->station->id,
            $day->copy()->setTime(12, 0),
            2
        );
        $this->assertSame(0, $availability['count'], 'Exactly 24h on day should block any additional slot (VEHICLE_24H / NO_VEHICLES)');
    }

    /**
     * Cross-midnight shift 20:00–04:00 counts 4h on day1 and 4h on day2.
     * Fill day1 to 24h => day1 additional fails; day2 still has capacity.
     */
    public function test_vehicle_cap_cross_midnight_counts_per_day(): void
    {
        $day1 = Carbon::create(2026, 3, 15, 0, 0, 0, 'UTC');
        $day2 = Carbon::create(2026, 3, 16, 0, 0, 0, 'UTC');

        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day1->copy()->setTime(20, 0),
            'ends_at' => $day2->copy()->setTime(4, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day1->copy()->setTime(0, 0),
            'ends_at' => $day1->copy()->setTime(20, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $service = app(ShiftAvailabilityService::class);

        $onDay1 = $service->checkAvailability(
            $this->station->id,
            $day1->copy()->setTime(10, 0),
            2
        );
        $this->assertSame(0, $onDay1['count'], 'Day1 has 24h (20h + 4h from cross-midnight); additional on day1 must fail');

        $onDay2 = $service->checkAvailability(
            $this->station->id,
            $day2->copy()->setTime(8, 0),
            4
        );
        $this->assertGreaterThanOrEqual(1, $onDay2['count'], 'Day2 has only 4h from cross-midnight; 4h slot on day2 must be allowed');
    }

    /**
     * Near 24h boundary: 22h + 2h (with gap) => pass; 24h already + 2h cross-midnight adding 1h on day => fail.
     */
    public function test_vehicle_cap_near_24h_boundary(): void
    {
        $day = Carbon::create(2026, 3, 15, 0, 0, 0, 'UTC');
        $service = app(ShiftAvailabilityService::class);

        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(0, 0),
            'ends_at' => $day->copy()->setTime(22, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $av22 = $service->checkAvailability($this->station->id, $day->copy()->setTime(23, 0), 2);
        $this->assertGreaterThanOrEqual(1, $av22['count'], '22h + 2h (23:00-01:00, 1h on day) = 23h; must pass');

        Shift::query()->where('vehicle_id', $this->vehicle->id)->delete();
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(0, 0),
            'ends_at' => $day->copy()->setTime(8, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(8, 0),
            'ends_at' => $day->copy()->setTime(16, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(16, 0),
            'ends_at' => $day->copy()->addDay()->setTime(0, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $av24 = $service->checkAvailability($this->station->id, $day->copy()->setTime(23, 0), 2);
        $this->assertSame(0, $av24['count'], '24h already; 2h (23:00-01:00) adds 1h on day1 => 25h; must fail');
    }

    /**
     * Calendar day is determined by policy timezone (e.g. Europe/Riga); cross-midnight split uses that TZ.
     */
    public function test_vehicle_cap_uses_policy_timezone_for_calendar_day(): void
    {
        $this->policy->update(['timezone' => 'Europe/Riga']);
        $this->policy->refresh();

        $day = Carbon::create(2026, 3, 15, 0, 0, 0, 'UTC');
        $service = app(ShiftAvailabilityService::class);

        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $day->copy()->setTime(20, 0),
            'ends_at' => $day->copy()->addDay()->setTime(4, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $availability = $service->checkAvailability(
            $this->station->id,
            $day->copy()->setTime(8, 0),
            4
        );
        $this->assertGreaterThanOrEqual(1, $availability['count'], 'Cross-midnight 20:00-04:00 UTC splits by Riga day; 08:00-12:00 UTC is different day in Riga, should have capacity');
    }
}
