<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftEditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftEditTest extends TestCase
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
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8, 10],
            'vehicle_downtime_hours' => 1,
            'time_slot_minutes' => 15,
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver = Driver::factory()->create();
    }

    public function test_can_edit_shift_when_enough_downtime_before_and_after(): void
    {
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(18, 0),
        ]);
        $service = app(ShiftEditService::class);
        $this->assertTrue($service->canEditShift($shift));
    }

    public function test_cannot_edit_shift_when_gap_before_less_than_downtime(): void
    {
        $prevEnd = Carbon::tomorrow()->setTime(9, 0);
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => Driver::factory()->create()->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(7, 0),
            'ends_at' => $prevEnd,
        ]);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(9, 30),
            'ends_at' => Carbon::tomorrow()->setTime(13, 30),
        ]);
        $service = app(ShiftEditService::class);
        $this->assertFalse($service->canEditShift($shift));
    }

    public function test_cannot_edit_shift_when_gap_after_less_than_downtime(): void
    {
        $nextStart = Carbon::tomorrow()->setTime(19, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(18, 30),
        ]);
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => Driver::factory()->create()->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => $nextStart,
            'ends_at' => Carbon::tomorrow()->setTime(22, 0),
        ]);
        $service = app(ShiftEditService::class);
        $this->assertFalse($service->canEditShift($shift));
    }

    public function test_update_shift_success(): void
    {
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(16, 0),
        ]);
        $service = app(ShiftEditService::class);
        $newStart = Carbon::tomorrow()->setTime(9, 0);
        $updated = $service->updateShift($shift, $newStart, 6);
        $this->assertSame($shift->id, $updated->id);
        $updated->refresh();
        $this->assertSame('09:00', $updated->starts_at->format('H:i'));
        $this->assertSame(6, (int) $updated->durationHours());
    }

    public function test_update_shift_throws_when_invalid_duration(): void
    {
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(16, 0),
        ]);
        $service = app(ShiftEditService::class);
        $newStart = Carbon::tomorrow()->setTime(9, 0);
        $this->expectException(ShiftBookingException::class);
        $this->expectExceptionMessage('Duration');
        $service->updateShift($shift, $newStart, 7);
    }

    public function test_update_shift_throws_when_new_slot_has_insufficient_downtime(): void
    {
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => Driver::factory()->create()->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(10, 0),
        ]);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'status' => ShiftStatus::Booked,
            'starts_at' => Carbon::tomorrow()->setTime(12, 0),
            'ends_at' => Carbon::tomorrow()->setTime(18, 0),
        ]);
        $service = app(ShiftEditService::class);
        $newStart = Carbon::tomorrow()->setTime(10, 30);
        $this->expectException(ShiftBookingException::class);
        $service->updateShift($shift, $newStart, 6);
    }
}
