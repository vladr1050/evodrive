<?php

namespace Tests\Unit\Services\Utilization;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\DriverUtilizationFilters;
use App\Services\Utilization\DriverUtilizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverUtilizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;

    private FleetVehicle $vehicle;

    private Driver $driver;

    private DriverUtilizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ShiftPolicy::factory()->create(['timezone' => 'Europe/Riga']);
        $this->station = Station::factory()->create(['name' => 'Riga Center']);
        $this->vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $this->station->id,
            'brand' => 'Tesla',
            'model' => '3',
            'registration_number' => 'EX-3899',
        ]);
        $this->driver = Driver::factory()->create(['name' => 'John Doe']);
        $this->service = new DriverUtilizationService('Europe/Riga');
    }

    public function test_same_day_planned_minutes(): void
    {
        $date = '2026-04-03';
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse($date.' 08:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($date.' 15:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($date, $date);
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOOKED);
        $rows = $this->service->getDailyDriverUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($date, $row->date);
        $this->assertSame($this->driver->id, $row->driver_id);
        $this->assertSame(420, $row->planned_minutes);
        $this->assertSame(0, $row->worked_minutes);
        $this->assertSame(420, $row->total_minutes);
        $this->assertSame(7.0, $row->total_hours);
    }

    public function test_cross_midnight_split(): void
    {
        $day1 = '2026-04-03';
        $day2 = '2026-04-04';
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse($day1.' 20:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($day2.' 02:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Completed,
        ]);

        $range = new DateRange($day1, $day2);
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_COMPLETED);
        $rows = $this->service->getDailyDriverUtilization($range, $filters);

        $this->assertCount(2, $rows);
        $byDate = $rows->keyBy('date');
        $this->assertSame(240, $byDate->get($day1)->worked_minutes);
        $this->assertSame(240, $byDate->get($day1)->total_minutes);
        $this->assertSame(120, $byDate->get($day2)->worked_minutes);
        $this->assertSame(120, $byDate->get($day2)->total_minutes);
    }

    public function test_overlapping_intervals_merge(): void
    {
        $date = '2026-04-03';
        $base = Carbon::parse($date.' 00:00:00', 'Europe/Riga')->setTimezone('UTC');
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $base->copy()->addHours(8),
            'ends_at' => $base->copy()->addHours(12),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $base->copy()->addHours(10),
            'ends_at' => $base->copy()->addHours(14),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($date, $date);
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOOKED);
        $rows = $this->service->getDailyDriverUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(480, $row->planned_minutes);
        $this->assertSame(360, $row->total_minutes);
    }

    public function test_planned_vs_worked(): void
    {
        $date = '2026-04-03';
        $base = Carbon::parse($date.' 00:00:00', 'Europe/Riga')->setTimezone('UTC');
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $base->copy()->addHours(0),
            'ends_at' => $base->copy()->addHours(4),
            'status' => ShiftStatus::Completed,
        ]);
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $base->copy()->addHours(5),
            'ends_at' => $base->copy()->addHours(9),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($date, $date);
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOTH);
        $rows = $this->service->getDailyDriverUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(240, $row->worked_minutes);
        $this->assertSame(240, $row->planned_minutes);
        $this->assertSame(480, $row->total_minutes);
    }

    public function test_get_driver_day_breakdown(): void
    {
        $date = '2026-04-03';
        $shift = Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse($date.' 09:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($date.' 12:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_BOOKED);
        $breakdown = $this->service->getDriverDayBreakdown($this->driver->id, $date, $filters);

        $this->assertCount(1, $breakdown);
        $this->assertSame($shift->id, $breakdown[0]['shift_id']);
        $this->assertSame('Riga Center', $breakdown[0]['station']);
        $this->assertSame(180, $breakdown[0]['duration_minutes']);
        $this->assertSame('booked', $breakdown[0]['status']);
    }

    public function test_daily_rows_only_include_days_inside_report_range(): void
    {
        $tz = 'Europe/Riga';
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse('2026-03-25 08:00:00', $tz)->utc(),
            'ends_at' => Carbon::parse('2026-04-20 18:00:00', $tz)->utc(),
            'status' => ShiftStatus::Completed,
        ]);

        $range = new DateRange('2026-04-01', '2026-04-07');
        $filters = new DriverUtilizationFilters(null, null, null, DriverUtilizationFilters::STATUS_MODE_COMPLETED, $tz);
        $rows = $this->service->getDailyDriverUtilization($range, $filters);

        $dates = $rows->pluck('date')->unique()->sort()->values()->all();
        $this->assertCount(7, $dates);
        $this->assertSame('2026-04-01', $dates[0]);
        $this->assertSame('2026-04-07', $dates[6]);
        foreach ($dates as $d) {
            $this->assertGreaterThanOrEqual('2026-04-01', $d);
            $this->assertLessThanOrEqual('2026-04-07', $d);
        }
    }
}
