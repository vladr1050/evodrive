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

class DriverPortalShiftEditTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;

    protected Station $station;

    protected FleetVehicle $vehicle;

    protected Driver $driver1;

    protected Driver $driver2;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 1,
            'time_slot_minutes' => 15,
            'timezone' => 'UTC',
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver1 = Driver::factory()->create();
        $this->driver2 = Driver::factory()->create();
    }

    public function test_driver_can_update_own_editable_shift(): void
    {
        $tomorrow = Carbon::tomorrow();
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $tomorrow->copy()->setTime(8, 0),
            'ends_at' => $tomorrow->copy()->setTime(16, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $newDate = $tomorrow->format('Y-m-d');
        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.update', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), [
                'date' => $newDate,
                'start_time' => '09:00',
                'duration_hours' => 6,
                '_token' => csrf_token(),
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $shift->refresh();
        $this->assertSame('09:00', $shift->starts_at->format('H:i'));
        $this->assertSame(6, (int) round($shift->durationHours()));
    }

    public function test_driver_cannot_update_other_driver_shift(): void
    {
        $tomorrow = Carbon::tomorrow();
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $tomorrow->copy()->setTime(8, 0),
            'ends_at' => $tomorrow->copy()->setTime(16, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $response = $this->actingAs($this->driver2, 'driver')
            ->postJson(route('driverportal.shifts.update', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), [
                'date' => $tomorrow->format('Y-m-d'),
                'start_time' => '09:00',
                'duration_hours' => 6,
                '_token' => csrf_token(),
            ]);

        $response->assertStatus(403);
        $shift->refresh();
        $this->assertSame('08:00', $shift->starts_at->format('H:i'));
    }

    public function test_driver_can_extend_ongoing_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-25 12:00:00', 'UTC'));
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => Carbon::parse('2026-04-25 10:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-04-25 14:00:00', 'UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.update', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), [
                'date' => '2026-04-25',
                'start_time' => '10:00',
                'duration_hours' => 6,
                'extend_ongoing' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $shift->refresh();
        $this->assertSame('10:00', $shift->starts_at->format('H:i'));
        $this->assertEqualsWithDelta(6.0, $shift->durationHours(), 0.01);
    }

    public function test_extend_ongoing_rejects_wrong_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-25 12:00:00', 'UTC'));
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => Carbon::parse('2026-04-25 10:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-04-25 14:00:00', 'UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.update', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), [
                'date' => '2026-04-25',
                'start_time' => '11:00',
                'duration_hours' => 6,
                'extend_ongoing' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertStatus(422);
        $response->assertJson(['reason_code' => 'EXTEND_START_MISMATCH']);
    }

    public function test_extend_ongoing_rejects_duration_not_in_allowed_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-25 12:00:00', 'UTC'));
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => Carbon::parse('2026-04-25 10:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-04-25 14:00:00', 'UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.update', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), [
                'date' => '2026-04-25',
                'start_time' => '10:00',
                'duration_hours' => 10,
                'extend_ongoing' => true,
                '_token' => csrf_token(),
            ]);

        $response->assertStatus(422);
        $response->assertJson(['reason_code' => 'INVALID_DURATION']);
    }
}
