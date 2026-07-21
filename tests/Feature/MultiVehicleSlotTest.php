<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftAvailabilityService;
use App\Services\ShiftBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiVehicleSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_slots_are_emitted_per_vehicle_not_merged(): void
    {
        ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 15,
            'timezone' => 'Europe/Riga',
        ]);
        $station = Station::factory()->create(['is_active' => true]);
        $v1 = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'AA-111',
            'brand' => 'Tesla',
            'model' => 'Y',
        ]);
        $v2 = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'BB-222',
            'brand' => 'Tesla',
            'model' => 'Y',
        ]);

        $tz = 'Europe/Riga';
        $weekStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY);
        $slots = app(ShiftAvailabilityService::class)->getAvailableSlotsForWeek(
            $weekStart,
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            $station->id
        );

        $this->assertNotEmpty($slots);
        $this->assertTrue(
            collect($slots)->every(fn ($s) => ($s['cars_count'] ?? 0) === 1),
            'Each free slot card must be for a single vehicle'
        );

        $vehicleIds = collect($slots)->pluck('vehicle_id')->unique()->sort()->values()->all();
        $this->assertSame([(int) $v1->id, (int) $v2->id], $vehicleIds);

        $tones = collect($slots)->groupBy('vehicle_id')->map(fn ($group) => $group->first()['vehicle_tone']);
        $this->assertNotSame($tones[(int) $v1->id], $tones[(int) $v2->id]);
    }

    public function test_preferred_vehicle_is_used_when_booking(): void
    {
        ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 15,
            'timezone' => 'Europe/Riga',
        ]);
        $station = Station::factory()->create(['is_active' => true]);
        $first = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $second = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        $startsAt = Carbon::tomorrow()->setTime(8, 0);
        $shift = app(ShiftBookingService::class)->bookShift(
            $driver->id,
            $station->id,
            $startsAt,
            4,
            (int) $second->id
        );

        $this->assertSame((int) $second->id, (int) $shift->vehicle_id);
        $this->assertNotSame((int) $first->id, (int) $shift->vehicle_id);
    }

    public function test_vehicle_with_booking_does_not_share_slot_with_free_sibling(): void
    {
        ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 15,
            'timezone' => 'Europe/Riga',
        ]);
        $station = Station::factory()->create(['is_active' => true]);
        $busy = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'BUSY-1',
        ]);
        $free = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'FREE-2',
        ]);
        $driver = Driver::factory()->create();

        $tz = 'Europe/Riga';
        $day = Carbon::now($tz)->startOfWeek(Carbon::MONDAY)->addDays(2)->setTime(10, 0);
        if ($day->lte(now($tz))) {
            $day = Carbon::now($tz)->addDay()->setTime(10, 0);
        }

        Shift::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $busy->id,
            'original_vehicle_id' => $busy->id,
            'station_id' => $station->id,
            'starts_at' => $day->copy()->utc(),
            'ends_at' => $day->copy()->addHours(8)->utc(),
            'status' => ShiftStatus::Booked,
        ]);

        $weekStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY);
        $slots = app(ShiftAvailabilityService::class)->getAvailableSlotsForWeek(
            $weekStart,
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            $station->id
        );

        $dayName = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day->dayOfWeekIso - 1];
        $sameWindow = collect($slots)->filter(function ($s) use ($dayName, $day) {
            return $s['day'] === $dayName
                && $s['date_iso'] === $day->format('Y-m-d')
                && $s['start'] <= '10:00'
                && $s['end'] >= '18:00';
        });

        $this->assertTrue(
            $sameWindow->contains(fn ($s) => (int) $s['vehicle_id'] === (int) $free->id),
            'Free vehicle should still have its own card'
        );
        $this->assertFalse(
            $sameWindow->contains(fn ($s) => (int) $s['vehicle_id'] === (int) $busy->id),
            'Busy vehicle must not appear in the overlapping free window'
        );
    }
}
