<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Jobs\SendShiftCancellationTelegramNotificationJob;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DriverPortalShiftCancelTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;
    protected Station $station;
    protected FleetVehicle $vehicle;
    protected Driver $driver1;
    protected Driver $driver2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'time_slot_minutes' => 15,
            'timezone' => 'UTC',
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver1 = Driver::factory()->create();
        $this->driver2 = Driver::factory()->create();
    }

    public function test_driver_can_cancel_own_future_shift(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver1, 'driver')->get(route('driverportal.shifts', ['locale' => 'en']));

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Cancelled, $shift->status);
        $this->assertNotNull($shift->cancelled_at);
        $this->assertSame('cancelled_by_driver', $shift->cancel_reason);
        $this->assertSame((int) $this->driver1->id, (int) $shift->cancelled_by_driver_id);
    }

    public function test_cancelling_shift_schedules_delayed_telegram_notification_job(): void
    {
        Queue::fake();

        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver1, 'driver');
        $this->get(route('driverportal.shifts', ['locale' => 'en']));
        $this->postJson(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()])
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(SendShiftCancellationTelegramNotificationJob::class, function (SendShiftCancellationTelegramNotificationJob $job) {
            if ($job->delay === null) {
                return false;
            }
            // Delay may be DateTimeInterface (e.g. Carbon) or seconds
            if ($job->delay instanceof \DateTimeInterface) {
                return $job->delay->getTimestamp() > time();
            }
            return (int) $job->delay >= 60;
        });
    }

    public function test_driver_cannot_cancel_other_drivers_shift(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver2, 'driver')->get(route('driverportal.shifts', ['locale' => 'en']));

        $response = $this->actingAs($this->driver2, 'driver')
            ->postJson(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertForbidden();
        $response->assertJson(['success' => false, 'reason_code' => 'FORBIDDEN']);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Booked, $shift->status);
    }

    public function test_driver_cannot_cancel_past_shift(): void
    {
        $startsAt = Carbon::yesterday()->setTime(8, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $this->actingAs($this->driver1, 'driver')->get(route('driverportal.shifts', ['locale' => 'en']));

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'reason_code' => 'SHIFT_IN_PAST']);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Booked, $shift->status);
    }

    public function test_driver_cannot_cancel_already_cancelled_shift(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Cancelled,
        ]);

        $this->actingAs($this->driver1, 'driver')->get(route('driverportal.shifts', ['locale' => 'en']));

        $response = $this->actingAs($this->driver1, 'driver')
            ->postJson(route('driverportal.shifts.cancel', [
                'locale' => 'en',
                'shift' => $shift->id,
            ]), ['_token' => csrf_token()]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'reason_code' => 'NOT_BOOKED']);
    }
}
