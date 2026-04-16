<?php

namespace Tests\Feature;

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

class VehicleUtilizationOriginalVehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_booked_minutes_follow_current_vehicle_by_default_and_original_when_flagged(): void
    {
        ShiftPolicy::factory()->create();
        $station = Station::factory()->create();
        $v1 = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $v2 = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        $tz = 'Europe/Riga';
        $startsAt = Carbon::now($tz)->addDay()->setTime(8, 0)->utc();
        $endsAt = $startsAt->copy()->addHours(4);

        $shift = Shift::factory()->create([
            'vehicle_id' => $v1->id,
            'station_id' => $station->id,
            'driver_id' => $driver->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ]);
        $shift->vehicle_id = $v2->id;
        $shift->save();
        $shift->refresh();
        $this->assertSame($v1->id, $shift->original_vehicle_id);
        $this->assertSame($v2->id, $shift->vehicle_id);

        $dateKey = $startsAt->copy()->timezone($tz)->format('Y-m-d');
        $range = new DateRange($dateKey, $dateKey);

        $service = app(VehicleUtilizationService::class);
        $filtersBooked = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED, $tz, false);
        $rowsDefault = $service->getDailyUtilization($range, $filtersBooked);
        $rowV2 = $rowsDefault->firstWhere('vehicle_id', $v2->id);
        $this->assertNotNull($rowV2);
        $this->assertSame(240, $rowV2->booked_minutes);
        $this->assertNull($rowsDefault->firstWhere('vehicle_id', $v1->id));

        $filtersOriginal = new UtilizationFilters(null, null, UtilizationFilters::STATUS_MODE_BOOKED, $tz, true);
        $rowsOriginal = $service->getDailyUtilization($range, $filtersOriginal);
        $rowV1 = $rowsOriginal->firstWhere('vehicle_id', $v1->id);
        $this->assertNotNull($rowV1);
        $this->assertSame(240, $rowV1->booked_minutes);
        $this->assertNull($rowsOriginal->firstWhere('vehicle_id', $v2->id));
    }
}
