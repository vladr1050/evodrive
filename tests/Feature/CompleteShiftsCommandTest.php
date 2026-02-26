<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteShiftsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2025-02-26 14:00:00', 'Europe/Riga'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Case A: shift ends_at <= now and status booked => becomes completed */
    public function test_past_ended_booked_shift_becomes_completed(): void
    {
        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        $endsAt = Carbon::now()->subHour();
        $startsAt = $endsAt->copy()->subHours(4);
        $shift = Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ]);

        $this->artisan('shifts:complete')
            ->assertSuccessful();

        $shift->refresh();
        $this->assertSame(ShiftStatus::Completed, $shift->status);
        $this->assertSame(1, Shift::where('status', ShiftStatus::Completed)->count());
    }

    /** Case B: shift cancelled must remain cancelled */
    public function test_cancelled_shift_remains_cancelled(): void
    {
        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        $endsAt = Carbon::now()->subHour();
        $startsAt = $endsAt->copy()->subHours(4);
        $shift = Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Cancelled,
        ]);

        $this->artisan('shifts:complete')
            ->assertSuccessful();

        $shift->refresh();
        $this->assertSame(ShiftStatus::Cancelled, $shift->status);
        $this->assertSame(1, Shift::where('status', ShiftStatus::Cancelled)->count());
        $this->assertSame(0, Shift::where('status', ShiftStatus::Completed)->count());
    }

    /** Case C: future booked shift remains booked */
    public function test_future_booked_shift_remains_booked(): void
    {
        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $endsAt = $startsAt->copy()->addHours(4);
        $shift = Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ]);

        $this->artisan('shifts:complete')
            ->assertSuccessful();

        $shift->refresh();
        $this->assertSame(ShiftStatus::Booked, $shift->status);
        $this->assertSame(1, Shift::where('status', ShiftStatus::Booked)->count());
        $this->assertSame(0, Shift::where('status', ShiftStatus::Completed)->count());
    }
}
