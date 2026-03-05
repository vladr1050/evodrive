<?php

namespace Tests\Unit\Services\Utilization;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\UtilizationFilters;
use App\Services\Utilization\VehicleUtilizationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleUtilizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Station $station;
    private FleetVehicle $vehicle;
    private Driver $driver;
    private VehicleUtilizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ShiftPolicy::factory()->create(['timezone' => 'Europe/Riga']);
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $this->station->id,
            'brand' => 'Tesla',
            'model' => '3',
            'registration_number' => 'EX-001',
        ]);
        $this->driver = Driver::factory()->create();
        $this->service = new VehicleUtilizationService('Europe/Riga');
    }

    public function test_same_day_interval(): void
    {
        $date = '2026-04-03';
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse($date . ' 08:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($date . ' 15:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($date, $date);
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED);
        $rows = $this->service->getDailyUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($date, $row->date);
        $this->assertSame($this->vehicle->id, $row->vehicle_id);
        $this->assertSame(420, $row->booked_minutes);
        $this->assertSame(0, $row->completed_minutes);
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
            'starts_at' => Carbon::parse($day1 . ' 20:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($day2 . ' 02:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Completed,
        ]);

        $range = new DateRange($day1, $day2);
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_COMPLETED);
        $rows = $this->service->getDailyUtilization($range, $filters);

        $this->assertCount(2, $rows);
        $byDate = $rows->keyBy('date');
        $this->assertSame(240, $byDate->get($day1)->completed_minutes);
        $this->assertSame(240, $byDate->get($day1)->total_minutes);
        $this->assertSame(120, $byDate->get($day2)->completed_minutes);
        $this->assertSame(120, $byDate->get($day2)->total_minutes);
    }

    public function test_overlapping_intervals_merge(): void
    {
        $date = '2026-04-03';
        $base = Carbon::parse($date . ' 00:00:00', 'Europe/Riga')->setTimezone('UTC');
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
            'driver_id' => Driver::factory()->create()->id,
            'starts_at' => $base->copy()->addHours(10),
            'ends_at' => $base->copy()->addHours(14),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($date, $date);
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED);
        $rows = $this->service->getDailyUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(480, $row->booked_minutes);
        $this->assertSame(360, $row->total_minutes);
    }

    public function test_cap_at_24h(): void
    {
        $day1 = '2026-04-03';
        $day2 = '2026-04-04';
        $base = Carbon::parse($day1 . ' 00:00:00', 'Europe/Riga')->setTimezone('UTC');
        Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => $base->copy(),
            'ends_at' => $base->copy()->addHours(25),
            'status' => ShiftStatus::Booked,
        ]);

        $range = new DateRange($day1, $day2);
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED);
        $rows = $this->service->getDailyUtilization($range, $filters);

        $this->assertCount(2, $rows);
        $byDate = $rows->keyBy('date');
        $this->assertSame(1440, $byDate->get($day1)->total_minutes);
        $this->assertSame(60, $byDate->get($day2)->total_minutes);
    }

    public function test_booked_and_completed_combined(): void
    {
        $date = '2026-04-03';
        $base = Carbon::parse($date . ' 00:00:00', 'Europe/Riga')->setTimezone('UTC');
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
        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOTH);
        $rows = $this->service->getDailyUtilization($range, $filters);

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame(240, $row->completed_minutes);
        $this->assertSame(240, $row->booked_minutes);
        $this->assertSame(480, $row->total_minutes);
    }

    public function test_get_daily_intervals(): void
    {
        $date = '2026-04-03';
        $shift = Shift::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'driver_id' => $this->driver->id,
            'starts_at' => Carbon::parse($date . ' 09:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'ends_at' => Carbon::parse($date . ' 12:00:00', 'Europe/Riga')->setTimezone('UTC'),
            'status' => ShiftStatus::Booked,
        ]);

        $filters = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED);
        $intervals = $this->service->getDailyIntervals($this->vehicle->id, $date, $filters);

        $this->assertCount(1, $intervals);
        $this->assertSame('shift', $intervals[0]['source_type']);
        $this->assertSame($shift->id, $intervals[0]['source_id']);
        $this->assertSame('booked', $intervals[0]['status']);
        $this->assertSame(180, $intervals[0]['minutes']);
    }
}
