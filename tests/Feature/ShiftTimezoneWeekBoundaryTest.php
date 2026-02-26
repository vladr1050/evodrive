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

class ShiftTimezoneWeekBoundaryTest extends TestCase
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
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'max_shifts_per_driver_per_day' => null,
            'time_slot_minutes' => 15,
            'planning_window_days' => 21,
            'timezone' => 'Europe/Riga',
        ]);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver = Driver::factory()->create();
    }

    /**
     * Previous week shift near boundary (Sunday 23:30 local) must map to target week Sunday 23:30 local.
     */
    public function test_copy_week_uses_iso_week_start_in_policy_timezone(): void
    {
        $tz = 'Europe/Riga';
        $prevWeekSundayRiga = Carbon::create(2026, 2, 22, 23, 30, 0, $tz);
        $prevWeekSundayUtc = $prevWeekSundayRiga->copy()->setTimezone('UTC');

        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevWeekSundayUtc,
            'ends_at' => $prevWeekSundayUtc->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $targetWeekStart = Carbon::create(2026, 2, 23, 0, 0, 0, $tz);
        Carbon::setTestNow(Carbon::create(2026, 2, 10, 12, 0, 0, $tz));

        $service = app(ShiftCopyService::class);
        $preview = $service->previewCopyWeek($this->driver, $targetWeekStart);

        $this->assertCount(1, $preview['proposed'], 'Sunday 23:30 Riga in previous week should map to target week Sunday 23:30 Riga');
        $this->assertCount(0, $preview['conflicts']);
        $proposed = $preview['proposed'][0];
        $this->assertSame('2026-03-01', $proposed['date'], 'Target week Sunday in Riga');
        $this->assertSame('23:30', $proposed['start_time'], 'Time preserved in policy timezone');

        Carbon::setTestNow();
    }

    /**
     * Planning window uses policy timezone (Europe/Riga), not server/app timezone.
     * Now = 23:30 UTC (so in Riga it is already next calendar day). Target week Monday in Riga
     * must still be within the planning window and the previous week's shift must map to it.
     */
    public function test_planning_window_respects_policy_timezone_not_server_timezone(): void
    {
        $tz = 'Europe/Riga';
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 23, 30, 0, 'UTC'));

        $policy = ShiftPolicy::active();
        $this->assertSame('Europe/Riga', $policy->timezone);
        $nowRiga = now($tz);
        $nowUtc = now('UTC');
        $this->assertNotSame($nowRiga->format('Y-m-d'), $nowUtc->format('Y-m-d'), 'Precondition: midnight boundary between UTC and Riga');

        $prevWeekMondayRiga = Carbon::create(2026, 2, 16, 8, 0, 0, $tz);
        $prevWeekMondayUtc = $prevWeekMondayRiga->copy()->setTimezone('UTC');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $prevWeekMondayUtc,
            'ends_at' => $prevWeekMondayUtc->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);

        $targetWeekStart = Carbon::create(2026, 2, 23, 0, 0, 0, $tz);
        $service = app(ShiftCopyService::class);
        $preview = $service->previewCopyWeek($this->driver, $targetWeekStart);

        $mondayInRigaDate = '2026-02-23';
        $hasMondayProposedOrConflict = collect($preview['proposed'])->contains('date', $mondayInRigaDate)
            || collect($preview['conflicts'])->contains('date', $mondayInRigaDate);
        $this->assertTrue($hasMondayProposedOrConflict, 'Target week Monday (Riga) should appear as proposed or conflict; planning window must use policy TZ not server');
        $outsideReasons = collect($preview['conflicts'])->pluck('reason_code')->filter(fn ($c) => $c === 'OUTSIDE_PLANNING_WINDOW')->count();
        $this->assertSame(0, $outsideReasons, 'No slot should be OUTSIDE_PLANNING_WINDOW when target week is within window in policy TZ');

        Carbon::setTestNow();
    }
}
