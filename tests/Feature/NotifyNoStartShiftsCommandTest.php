<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyNoStartShiftsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'telegram.bot_token' => 'test-token',
            'telegram.shifts_chat_id' => '-100123',
            'telegram.no_start_grace_minutes' => 60,
            'telegram.no_start_lookback_hours' => 24,
        ]);
        ShiftPolicy::factory()->create(['timezone' => 'Europe/Riga']);
    }

    public function test_notifies_when_driver_has_not_started_via_bot_after_grace(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $now = Carbon::parse('2030-06-02 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $station = Station::factory()->create(['name' => 'Depot', 'address' => '1 St']);
        $vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'LV-100',
        ]);
        $driver = Driver::factory()->create(['first_name' => 'Late', 'last_name' => 'Driver']);

        $shift = Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $now->copy()->subMinutes(90),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
            'started_via_bot_at' => null,
            'no_start_notified_at' => null,
        ]);

        $this->artisan('shifts:notify-no-start')->assertSuccessful();

        $shift->refresh();
        $this->assertNotNull($shift->no_start_notified_at);

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';

            return str_contains($request->url(), 'sendMessage')
                && str_contains($text, 'Driver did not start shift')
                && str_contains($text, 'Depot')
                && str_contains($text, 'LV-100')
                && str_contains($text, 'Late Driver');
        });

        Carbon::setTestNow();
    }

    public function test_skips_when_started_via_bot(): void
    {
        Http::fake();

        $now = Carbon::parse('2030-06-02 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $now->copy()->subMinutes(90),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
            'started_via_bot_at' => $now->copy()->subMinutes(80),
            'no_start_notified_at' => null,
        ]);

        $this->artisan('shifts:notify-no-start')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_skips_when_already_notified(): void
    {
        Http::fake();

        $now = Carbon::parse('2030-06-02 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $now->copy()->subMinutes(90),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
            'started_via_bot_at' => null,
            'no_start_notified_at' => $now->copy()->subMinutes(10),
        ]);

        $this->artisan('shifts:notify-no-start')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_skips_when_still_inside_grace_window(): void
    {
        Http::fake();

        $now = Carbon::parse('2030-06-02 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        $station = Station::factory()->create();
        $vehicle = FleetVehicle::factory()->create(['home_station_id' => $station->id]);
        $driver = Driver::factory()->create();

        Shift::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'station_id' => $station->id,
            'starts_at' => $now->copy()->subMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
            'started_via_bot_at' => null,
            'no_start_notified_at' => null,
        ]);

        $this->artisan('shifts:notify-no-start')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow();
    }
}
