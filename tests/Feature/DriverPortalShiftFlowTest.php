<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPortalShiftFlowTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;
    protected Station $station;
    protected FleetVehicle $vehicle;
    protected Driver $driver;
    protected Driver $otherDriver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'timezone' => 'Europe/Riga',
            'allowed_durations_json' => [4, 6, 8],
            'planning_window_days' => 14,
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $this->station->id,
        ]);
        $this->driver = Driver::factory()->create();
        $this->otherDriver = Driver::factory()->create();
    }

    public function test_check_availability_returns_count_when_vehicles_available(): void
    {
        $this->actingAs($this->driver, 'driver')->get('/en/driverportal/shifts');

        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->postJson(route('driverportal.shifts.check-availability', ['locale' => 'en']), [
                '_token' => csrf_token(),
                'station_id' => $this->station->id,
                'date' => $tomorrow,
                'start_time' => '08:00',
                'duration_hours' => 4,
            ]);

        $response->assertOk();
        $response->assertJson([
            'available' => true,
            'count' => 1,
        ]);
    }

    public function test_confirm_booking_creates_shift_with_booked_status(): void
    {
        $this->actingAs($this->driver, 'driver')->get('/en/driverportal/shifts');

        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->postJson(route('driverportal.shifts.confirm', ['locale' => 'en']), [
                '_token' => csrf_token(),
                'station_id' => $this->station->id,
                'date' => $tomorrow,
                'start_time' => '08:00',
                'duration_hours' => 4,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $shift = Shift::where('driver_id', $this->driver->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftStatus::Booked, $shift->status);
    }

    public function test_driver_can_cancel_own_future_shift(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);

        $shift = Shift::create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver, 'driver')->get('/en/driverportal/shifts');

        $response = $this->post(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Cancelled, $shift->status);
    }

    public function test_driver_cannot_cancel_other_drivers_shift(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);

        $shift = Shift::create([
            'driver_id' => $this->otherDriver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver, 'driver')->get('/en/driverportal/shifts');

        $response = $this->post(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertForbidden();
        $response->assertJson(['success' => false]);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Booked, $shift->status);
    }
}
