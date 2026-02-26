<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftAvailabilityService;
use App\Services\ShiftBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ShiftBookingTest extends TestCase
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
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver1 = Driver::factory()->create();
        $this->driver2 = Driver::factory()->create();
    }

    public function test_two_drivers_same_station_time_only_one_succeeds(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $bookingService = app(ShiftBookingService::class);

        $shift1 = $bookingService->bookShift($this->driver1->id, $this->station->id, $startsAt, 4);
        $this->assertSame($this->driver1->id, $shift1->driver_id);
        $this->assertSame($this->vehicle->id, $shift1->vehicle_id);

        $thrown = null;
        try {
            $bookingService->bookShift($this->driver2->id, $this->station->id, $startsAt, 4);
        } catch (ShiftBookingException $e) {
            $thrown = $e;
        }
        $this->assertInstanceOf(ShiftBookingException::class, $thrown);
        $this->assertSame('NO_VEHICLES', $thrown->reasonCode);
        $this->assertStringContainsString('No vehicles available', $thrown->getMessage());

        $this->assertSame(1, Shift::where('station_id', $this->station->id)
            ->where('starts_at', $startsAt)
            ->where('status', ShiftStatus::Booked)
            ->count(), 'Exactly one shift must exist for this station/time');
    }

    public function test_downtime_makes_vehicle_unavailable_so_second_booking_fails(): void
    {
        ShiftPolicy::query()->update(['vehicle_downtime_hours' => 2]);
        $this->policy->refresh();

        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => Carbon::tomorrow()->setTime(8, 0),
            'ends_at' => Carbon::tomorrow()->setTime(10, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $bookingService = app(ShiftBookingService::class);
        $startsAt = Carbon::tomorrow()->setTime(11, 0);

        $this->expectException(ShiftBookingException::class);
        $this->expectExceptionMessage('No vehicles available');
        $bookingService->bookShift($this->driver2->id, $this->station->id, $startsAt, 4);
    }

    public function test_daily_limit_violation(): void
    {
        ShiftPolicy::query()->update(['max_shifts_per_driver_per_day' => 1]);
        $this->policy->refresh();

        $day = Carbon::tomorrow();
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => $day->copy()->setTime(8, 0),
            'ends_at' => $day->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $bookingService = app(ShiftBookingService::class);

        $this->expectException(ShiftBookingException::class);
        $this->expectExceptionMessage('Maximum shifts per day');
        $bookingService->bookShift($this->driver1->id, $this->station->id, $day->copy()->setTime(14, 0), 4);
    }

    public function test_vehicle_24h_cap(): void
    {
        $day = Carbon::tomorrow();
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => $day->copy()->setTime(0, 0),
            'ends_at' => $day->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver2->id,
            'starts_at' => $day->copy()->setTime(12, 0),
            'ends_at' => $day->copy()->addDay()->startOfDay(),
            'status' => ShiftStatus::Booked,
        ]);
        $availability = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $day->copy()->setTime(20, 0),
            4
        );
        $this->assertSame(0, $availability['count']);
    }

    public function test_invalid_duration(): void
    {
        $bookingService = app(ShiftBookingService::class);
        $startsAt = Carbon::tomorrow()->setTime(8, 0);

        $this->expectException(ShiftBookingException::class);
        $this->expectExceptionMessage('Duration not allowed');
        $bookingService->bookShift($this->driver1->id, $this->station->id, $startsAt, 5);
    }

    /** Directional downtime: gap exactly equals required downtime => allowed */
    public function test_downtime_gap_exactly_equals_downtime_allowed(): void
    {
        $this->policy->update(['vehicle_downtime_hours' => 1]);
        $this->policy->refresh();

        $day = Carbon::tomorrow();
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => $day->copy()->setTime(8, 0),
            'ends_at' => $day->copy()->setTime(9, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $availability = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $day->copy()->setTime(10, 0),
            4
        );
        $this->assertGreaterThanOrEqual(1, $availability['count'], 'Gap of 60 min with 1h downtime should be allowed');
    }

    /** Directional downtime: gap 1 minute less than required => blocked (no vehicles) */
    public function test_downtime_gap_one_minute_less_blocked(): void
    {
        $this->policy->update(['vehicle_downtime_hours' => 1]);
        $this->policy->refresh();

        $day = Carbon::tomorrow();
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => $day->copy()->setTime(8, 0),
            'ends_at' => $day->copy()->setTime(9, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $startsAt = $day->copy()->setTime(9, 45);
        $availability = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $startsAt,
            4
        );
        $this->assertSame(0, $availability['count'], 'Gap of 45 min with 1h downtime should be blocked');

        $this->expectException(ShiftBookingException::class);
        $this->expectExceptionMessage('No vehicles available');
        app(ShiftBookingService::class)->bookShift($this->driver2->id, $this->station->id, $startsAt, 4);
    }

    /** Directional: requested shift ending exactly when next starts (gap 0) must be blocked, not allowed by abs masking */
    public function test_downtime_requested_ends_at_next_start_blocked(): void
    {
        $this->policy->update(['allowed_durations_json' => [2, 4, 6, 8], 'min_duration_hours' => 2]);
        $this->policy->refresh();

        $day = Carbon::tomorrow();
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver1->id,
            'starts_at' => $day->copy()->setTime(10, 0),
            'ends_at' => $day->copy()->setTime(12, 0),
            'status' => ShiftStatus::Booked,
        ]);
        $availability = app(ShiftAvailabilityService::class)->checkAvailability(
            $this->station->id,
            $day->copy()->setTime(8, 0),
            2
        );
        $this->assertSame(0, $availability['count'], 'Requested 08:00-10:00 with existing 10:00-12:00 must be blocked (next gap 0)');
    }

    /**
     * Race condition: two concurrent bookings for same station/time/duration with 1 vehicle.
     * Asserts exactly one shift is created and the second attempt fails with NO_VEHICLES or OVERLAP.
     * Requires PostgreSQL for proper row-level locking. Skips on SQLite.
     */
    public function test_concurrent_booking_same_slot_exactly_one_shift_second_fails(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Concurrent booking test requires PostgreSQL. Run with DB_CONNECTION=pgsql.');
        }

        $policy = ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 15,
        ]);
        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver1 = Driver::factory()->create();
        $driver2 = Driver::factory()->create();

        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $startsAtIso = $startsAt->toIso8601String();
        $basePath = base_path();
        $script = $basePath . '/tests/scripts/try_booking.php';

        $env = array_merge(getenv() ?: [], [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
            'APP_ENV' => 'testing',
        ]);

        $proc1 = new Process(
            ['php', $script, (string) $driver1->id, (string) $station->id, $startsAtIso, '4'],
            $basePath,
            $env
        );
        $proc2 = new Process(
            ['php', $script, (string) $driver2->id, (string) $station->id, $startsAtIso, '4'],
            $basePath,
            $env
        );
        $proc1->start();
        $proc2->start();
        $proc1->wait();
        $proc2->wait();

        $out1 = trim($proc1->getOutput());
        $out2 = trim($proc2->getOutput());
        $err1 = $proc1->getErrorOutput();
        $err2 = $proc2->getErrorOutput();
        $successCount = (int) ($out1 === 'SUCCESS') + (int) ($out2 === 'SUCCESS');
        $otherOut = $out1 === 'SUCCESS' ? $out2 : $out1;
        $otherErr = $out1 === 'SUCCESS' ? $err2 : $err1;
        $secondFailed = str_contains($otherOut, 'FAIL:') || str_contains($otherOut, 'database is locked')
            || str_contains($otherErr, 'database is locked');

        $this->assertSame(1, $successCount, 'Exactly one booking must succeed. Outputs: ' . $out1 . ' / ' . $out2);
        $this->assertTrue($secondFailed, 'Second attempt must fail (NO_VEHICLES, OVERLAP, or DB lock). Out: ' . $out1 . ' / ' . $out2 . ' Err: ' . $err1 . ' / ' . $err2);

        $shiftCount = Shift::where('station_id', $station->id)
            ->where('starts_at', $startsAt)
            ->where('status', ShiftStatus::Booked)
            ->count();
        $this->assertSame(1, $shiftCount, 'Exactly one shift must exist for this station/time');
    }
}
