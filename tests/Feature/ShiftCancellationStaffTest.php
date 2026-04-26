<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Events\ShiftCancelled;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Models\User;
use App\Services\ShiftCancellationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ShiftCancellationStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cancel_sets_user_and_dispatches_event_like_driver_flow(): void
    {
        ShiftPolicy::factory()->create(['timezone' => 'Europe/Riga']);
        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();
        $staff = User::factory()->create(['name' => 'Pat Manager']);

        $startsAt = Carbon::tomorrow()->setTime(9, 0);
        $shift = Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        Event::fake();

        app(ShiftCancellationService::class)->cancelByStaff($shift, $staff);

        $shift->refresh();
        $this->assertSame(ShiftStatus::Cancelled, $shift->status);
        $this->assertSame('cancelled_by_staff', $shift->cancel_reason);
        $this->assertSame($staff->id, $shift->cancelled_by_user_id);
        $this->assertNull($shift->cancelled_by_driver_id);

        $this->assertDatabaseHas('shift_events', [
            'shift_id' => $shift->id,
            'action' => 'cancelled',
            'performed_by_type' => 'admin',
            'performed_by_id' => $staff->id,
        ]);

        Event::assertDispatched(ShiftCancelled::class, function (ShiftCancelled $e) use ($driver, $shift) {
            return (int) $e->driver->id === (int) $driver->id
                && (int) $e->shift->id === (int) $shift->id;
        });
    }
}
