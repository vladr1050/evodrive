<?php

namespace Tests\Unit;

use App\Enums\DriverStatus;
use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\Station;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\DriverFleetInsightsService;
use App\Services\Utilization\DriverUtilizationFilters;
use App\Services\Utilization\DriverUtilizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverFleetInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_all_fleet_drivers_with_zeros_median_and_novice(): void
    {
        $tz = 'Europe/Riga';
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', $tz));

        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $dHigh = Driver::factory()->create(['first_name' => 'High', 'last_name' => 'Hours']);
        $dMid = Driver::factory()->create(['first_name' => 'Mid', 'last_name' => 'Hours']);
        $dZero = Driver::factory()->create(['first_name' => 'Zero', 'last_name' => 'New']);

        $day = Carbon::parse('2026-06-10', $tz)->setTime(8, 0)->utc();
        Shift::factory()->create([
            'driver_id' => $dHigh->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $day,
            'ends_at' => $day->copy()->addHours(8),
            'status' => ShiftStatus::Completed,
        ]);
        Shift::factory()->create([
            'driver_id' => $dMid->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $day,
            'ends_at' => $day->copy()->addHours(4),
            'status' => ShiftStatus::Completed,
        ]);

        $range = new DateRange('2026-06-10', '2026-06-10');
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOTH, $tz);
        $rows = app(DriverUtilizationService::class)->getDailyDriverUtilization($range, $filters);

        $fleetIds = [$dHigh->id, $dMid->id, $dZero->id];
        $insights = app(DriverFleetInsightsService::class)->build($rows, $range, $filters, $fleetIds, $tz, 30);

        $this->assertSame(4.0, $insights->median_worked_hours);
        $byId = $insights->rows->keyBy('driver_id');

        $this->assertSame(8.0, $byId[$dHigh->id]->worked_hours);
        $this->assertSame(4.0, $byId[$dMid->id]->worked_hours);
        $this->assertSame(0.0, $byId[$dZero->id]->worked_hours);
        $this->assertTrue($byId[$dZero->id]->is_novice);
        $this->assertFalse($byId[$dHigh->id]->is_novice);
        $this->assertSame(8.0, $byId[$dHigh->id]->avg_completed_booked_hours_per_day);
        $this->assertSame(4.0, $byId[$dMid->id]->avg_completed_booked_hours_per_day);
        $this->assertSame(0.0, $byId[$dZero->id]->avg_completed_booked_hours_per_day);

        $this->assertSame(
            [$dZero->id, $dHigh->id, $dMid->id],
            $insights->rows->pluck('driver_id')->all(),
            'Novices first, then highest activity score before lower score'
        );

        Carbon::setTestNow();
    }

    public function test_default_fleet_driver_ids_respects_status_filter(): void
    {
        $active = Driver::factory()->create(['status' => DriverStatus::Active]);
        $inactive = Driver::factory()->create(['status' => DriverStatus::Inactive]);

        $ids = app(DriverFleetInsightsService::class)->defaultFleetDriverIds([DriverStatus::Active]);

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_avg_completed_booked_hours_per_day_uses_full_range_length(): void
    {
        $tz = 'Europe/Riga';
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', $tz));

        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $dIdle = Driver::factory()->create();
        $dTen = Driver::factory()->create();

        $shiftStart = Carbon::parse('2026-06-10', $tz)->setTime(8, 0)->utc();
        Shift::factory()->create([
            'driver_id' => $dTen->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $shiftStart,
            'ends_at' => $shiftStart->copy()->addHours(8),
            'status' => ShiftStatus::Completed,
        ]);

        $range = new DateRange('2026-06-10', '2026-06-11');
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOTH, $tz);
        $rows = app(DriverUtilizationService::class)->getDailyDriverUtilization($range, $filters);

        $fleetIds = [$dIdle->id, $dTen->id];
        $insights = app(DriverFleetInsightsService::class)->build($rows, $range, $filters, $fleetIds, $tz, 30);

        $this->assertSame(2, $insights->day_count);
        $byId = $insights->rows->keyBy('driver_id');
        $this->assertSame(4.0, $byId[$dTen->id]->avg_completed_booked_hours_per_day);
        $this->assertSame(0.0, $byId[$dIdle->id]->avg_completed_booked_hours_per_day);

        Carbon::setTestNow();
    }
}
