<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftCopyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCopyWeekTest extends TestCase
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
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'time_slot_minutes' => 15,
            'planning_window_days' => 21,
            'timezone' => 'UTC',
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver1 = Driver::factory()->create();
        $this->driver2 = Driver::factory()->create();
    }

    public function test_copy_week_happy_path(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $prevMonday = Carbon::create(2026, 2, 16, 0, 0, 0);
        $targetMonday = Carbon::create(2026, 2, 23, 0, 0, 0);

        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->addDay()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->addDay()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->addDays(2)->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->addDays(2)->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $service = app(ShiftCopyService::class);
        $preview = $service->previewCopyWeek($this->driver1, $targetMonday);

        $this->assertCount(3, $preview['proposed']);
        $this->assertCount(0, $preview['conflicts']);

        $selections = array_map(fn ($p) => [
            'station_id' => $p['station_id'],
            'starts_at' => $p['date'] . ' ' . $p['start_time'] . ':00',
            'duration_hours' => $p['duration_hours'],
        ], $preview['proposed']);

        $confirm = $service->confirmCopyWeek($this->driver1, $selections);
        $this->assertTrue($confirm['success']);
        $this->assertCount(3, $confirm['shifts']);

        $this->assertDatabaseCount('shifts', 6);
        Carbon::setTestNow();
    }

    public function test_copy_week_partial_conflict(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $prevMonday = Carbon::create(2026, 2, 16, 0, 0, 0);
        $targetMonday = Carbon::create(2026, 2, 23, 0, 0, 0);

        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->addDay()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->addDay()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->addDays(2)->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->addDays(2)->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);

        // Pre-book the only vehicle on target Monday 8–12 so driver1's Monday copy has no vehicles
        Shift::factory()->create([
            'driver_id' => $this->driver2->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $targetMonday->copy()->setTime(8, 0),
            'ends_at' => $targetMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $service = app(ShiftCopyService::class);
        $preview = $service->previewCopyWeek($this->driver1, $targetMonday);

        $this->assertCount(2, $preview['proposed']);
        $this->assertCount(1, $preview['conflicts']);
        $this->assertSame('NO_VEHICLES', $preview['conflicts'][0]['reason_code']);
        Carbon::setTestNow();
    }

    public function test_copy_week_race_condition(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $prevMonday = Carbon::create(2026, 2, 16, 0, 0, 0);
        $targetMonday = Carbon::create(2026, 2, 23, 0, 0, 0);

        $vehicle2 = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver2->id,
            'vehicle_id' => $vehicle2->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $this->driver2->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $targetMonday->copy()->setTime(8, 0),
            'ends_at' => $targetMonday->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $service = app(ShiftCopyService::class);
        $preview1 = $service->previewCopyWeek($this->driver1, $targetMonday);
        $preview2 = $service->previewCopyWeek($this->driver2, $targetMonday);
        $this->assertCount(1, $preview1['proposed']);
        $this->assertCount(1, $preview2['proposed']);

        $sel1 = [
            'station_id' => $preview1['proposed'][0]['station_id'],
            'starts_at' => $preview1['proposed'][0]['date'] . ' ' . $preview1['proposed'][0]['start_time'] . ':00',
            'duration_hours' => $preview1['proposed'][0]['duration_hours'],
        ];
        $confirm1 = $service->confirmCopyWeek($this->driver1, [$sel1]);
        $this->assertTrue($confirm1['success']);

        $sel2 = [
            'station_id' => $preview2['proposed'][0]['station_id'],
            'starts_at' => $preview2['proposed'][0]['date'] . ' ' . $preview2['proposed'][0]['start_time'] . ':00',
            'duration_hours' => $preview2['proposed'][0]['duration_hours'],
        ];
        $confirm2 = $service->confirmCopyWeek($this->driver2, [$sel2]);
        $this->assertFalse($confirm2['success']);
        $this->assertArrayHasKey('conflicts', $confirm2);
        $this->assertSame('NO_VEHICLES', $confirm2['conflicts'][0]['reason_code']);
        Carbon::setTestNow();
    }

    public function test_copy_week_invalid_duration_due_to_policy_change(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0));
        $prevMonday = Carbon::create(2026, 2, 16, 0, 0, 0);
        $targetMonday = Carbon::create(2026, 2, 23, 0, 0, 0);

        Shift::factory()->create([
            'driver_id' => $this->driver1->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevMonday->copy()->setTime(8, 0),
            'ends_at' => $prevMonday->copy()->setTime(14, 0),
            'status' => ShiftStatus::Booked,
        ]);

        $this->policy->update(['allowed_durations_json' => [4, 8]]);
        $this->policy->refresh();

        $service = app(ShiftCopyService::class);
        $preview = $service->previewCopyWeek($this->driver1, $targetMonday);

        $this->assertCount(0, $preview['proposed']);
        $this->assertCount(1, $preview['conflicts']);
        $this->assertSame('INVALID_DURATION', $preview['conflicts'][0]['reason_code']);
        Carbon::setTestNow();
    }
}
