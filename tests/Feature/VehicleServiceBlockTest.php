<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Exceptions\VehicleServiceBlockException;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleServiceBlock;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftAvailabilityService;
use App\Services\VehicleServiceBlockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleServiceBlockTest extends TestCase
{
    use RefreshDatabase;

    protected Station $station;

    protected FleetVehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        ShiftPolicy::factory()->create([
            'timezone' => 'UTC',
            'vehicle_downtime_hours' => 1,
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
    }

    public function test_create_block_when_range_free(): void
    {
        $svc = app(VehicleServiceBlockService::class);
        $s = Carbon::parse('2026-05-01 08:00:00', 'UTC');
        $e = Carbon::parse('2026-05-01 18:00:00', 'UTC');
        $block = $svc->create($this->vehicle->id, $s, $e, 'Brakes', null);
        $this->assertSame($this->vehicle->id, $block->fleet_vehicle_id);
        $this->assertTrue($block->starts_at->equalTo($s));
        $this->assertTrue($block->ends_at->equalTo($e));
    }

    public function test_create_throws_when_overlaps_shift(): void
    {
        $s = Carbon::parse('2026-05-01 10:00:00', 'UTC');
        $e = Carbon::parse('2026-05-01 14:00:00', 'UTC');
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => \App\Models\Driver::factory(),
            'starts_at' => Carbon::parse('2026-05-01 11:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-01 12:00:00', 'UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $this->expectException(VehicleServiceBlockException::class);
        app(VehicleServiceBlockService::class)->create(
            $this->vehicle->id,
            Carbon::parse('2026-05-01 08:00:00', 'UTC'),
            Carbon::parse('2026-05-01 18:00:00', 'UTC'),
            null,
            null
        );
    }

    public function test_suggestions_present_when_shift_splits_range(): void
    {
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => \App\Models\Driver::factory(),
            'starts_at' => Carbon::parse('2026-05-01 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-01 13:00:00', 'UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        try {
            app(VehicleServiceBlockService::class)->create(
                $this->vehicle->id,
                Carbon::parse('2026-05-01 08:00:00', 'UTC'),
                Carbon::parse('2026-05-01 18:00:00', 'UTC'),
                null,
                null
            );
            $this->fail('Expected exception');
        } catch (VehicleServiceBlockException $e) {
            $this->assertSame('OVERLAPS_SHIFTS', $e->reasonCode);
            $this->assertNotEmpty($e->suggestions);
        }
    }

    public function test_overlapping_service_blocks_rejected(): void
    {
        $svc = app(VehicleServiceBlockService::class);
        $svc->create(
            $this->vehicle->id,
            Carbon::parse('2026-05-02 08:00:00', 'UTC'),
            Carbon::parse('2026-05-02 12:00:00', 'UTC'),
            null,
            null
        );

        $this->expectException(VehicleServiceBlockException::class);
        $svc->create(
            $this->vehicle->id,
            Carbon::parse('2026-05-02 10:00:00', 'UTC'),
            Carbon::parse('2026-05-02 14:00:00', 'UTC'),
            null,
            null
        );
    }

    public function test_vehicle_unavailable_during_service_for_booking(): void
    {
        $policy = ShiftPolicy::active();
        $this->assertNotNull($policy);
        $svc = app(VehicleServiceBlockService::class);
        $svc->create(
            $this->vehicle->id,
            Carbon::parse('2026-05-03 08:00:00', 'UTC'),
            Carbon::parse('2026-05-03 20:00:00', 'UTC'),
            null,
            null
        );

        $starts = Carbon::parse('2026-05-03 09:00:00', $policy->timezone);
        $avail = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $starts,
            8.0
        );
        $this->assertSame(0, $avail['count']);
        $this->assertNotContains($this->vehicle->id, $avail['vehicle_ids']);
    }

    public function test_complete_early_shortens_block(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00', 'UTC'));
        $block = FleetVehicleServiceBlock::query()->create([
            'fleet_vehicle_id' => $this->vehicle->id,
            'starts_at' => Carbon::parse('2026-05-04 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-04 20:00:00', 'UTC'),
        ]);

        app(VehicleServiceBlockService::class)->completeEarly($block);
        $block->refresh();
        $this->assertTrue($block->ends_at->equalTo(Carbon::parse('2026-05-04 10:00:00', 'UTC')));
        Carbon::setTestNow();
    }

    public function test_cancelled_block_does_not_block(): void
    {
        $policy = ShiftPolicy::active();
        $block = FleetVehicleServiceBlock::query()->create([
            'fleet_vehicle_id' => $this->vehicle->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 20:00:00', 'UTC'),
            'cancelled_at' => Carbon::parse('2026-05-05 07:00:00', 'UTC'),
        ]);

        $starts = Carbon::parse('2026-05-05 09:00:00', $policy->timezone);
        $avail = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $starts,
            8.0
        );
        $this->assertGreaterThanOrEqual(1, $avail['count']);
        $this->assertContains($this->vehicle->id, $avail['vehicle_ids']);
    }
}
