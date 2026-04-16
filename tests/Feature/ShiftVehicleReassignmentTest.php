<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\ShiftVehicleReassignmentException;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\ShiftVehicleReplacement;
use App\Models\Station;
use App\Services\ShiftVehicleReassignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftVehicleReassignmentTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;

    protected Station $station;

    protected FleetVehicle $vehicleA;

    protected FleetVehicle $vehicleB;

    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = ShiftPolicy::factory()->create([
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
        ]);
        $this->station = Station::factory()->create();
        $this->vehicleA = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->vehicleB = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver = Driver::factory()->create();
    }

    public function test_same_vehicle_throws(): void
    {
        $this->expectException(ShiftVehicleReassignmentException::class);
        $this->expectExceptionMessage('different');
        app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $this->vehicleA->id,
            Carbon::now('Europe/Riga'),
            null,
            null
        );
    }

    public function test_different_home_station_throws(): void
    {
        $otherStation = Station::factory()->create();
        $foreign = FleetVehicle::factory()->create(['home_station_id' => $otherStation->id]);

        $this->expectException(ShiftVehicleReassignmentException::class);
        $this->expectExceptionMessage('same home station');
        app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $foreign->id,
            Carbon::now('Europe/Riga'),
            null,
            null
        );
    }

    public function test_inactive_target_throws(): void
    {
        $this->vehicleB->status = VehicleStatus::Maintenance;
        $this->vehicleB->save();

        $this->expectException(ShiftVehicleReassignmentException::class);
        $this->expectExceptionMessage('active');
        app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $this->vehicleB->id,
            Carbon::now('Europe/Riga'),
            null,
            null
        );
    }

    public function test_future_booked_moves_vehicle_and_keeps_original_and_journal(): void
    {
        $tz = 'Europe/Riga';
        $startsAt = Carbon::now($tz)->addDay()->setTime(8, 0)->utc();
        $endsAt = $startsAt->copy()->addHours(4);

        $shift = Shift::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ]);
        $shift->refresh();
        $this->assertSame($this->vehicleA->id, $shift->original_vehicle_id);

        $effective = Carbon::now($tz)->startOfDay();
        $result = app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $this->vehicleB->id,
            $effective,
            null,
            'swap note'
        );

        $this->assertSame(1, $result['updated']);
        $this->assertNotEmpty($result['batch_id']);

        $shift->refresh();
        $this->assertSame($this->vehicleB->id, $shift->vehicle_id);
        $this->assertSame($this->vehicleA->id, $shift->original_vehicle_id);

        $this->assertDatabaseHas('shift_vehicle_replacements', [
            'shift_id' => $shift->id,
            'from_vehicle_id' => $this->vehicleA->id,
            'to_vehicle_id' => $this->vehicleB->id,
            'batch_id' => $result['batch_id'],
            'note' => 'swap note',
        ]);
        $this->assertSame(1, ShiftVehicleReplacement::query()->where('batch_id', $result['batch_id'])->count());
    }

    public function test_completed_shift_is_not_moved(): void
    {
        $tz = 'Europe/Riga';
        $startsAt = Carbon::now($tz)->addDay()->setTime(8, 0)->utc();
        $completed = Shift::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Completed,
        ]);

        app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $this->vehicleB->id,
            Carbon::now($tz)->subDay(),
            null,
            null
        );

        $completed->refresh();
        $this->assertSame($this->vehicleA->id, $completed->vehicle_id);
    }

    public function test_booked_shift_before_effective_from_is_not_moved(): void
    {
        $tz = 'Europe/Riga';
        $startsAt = Carbon::now($tz)->subDays(2)->setTime(8, 0)->utc();
        $shift = Shift::factory()->create([
            'vehicle_id' => $this->vehicleA->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        app(ShiftVehicleReassignmentService::class)->reassignFutureBookedShifts(
            $this->vehicleA->id,
            $this->vehicleB->id,
            Carbon::now($tz)->startOfDay(),
            null,
            null
        );

        $shift->refresh();
        $this->assertSame($this->vehicleA->id, $shift->vehicle_id);
    }
}
