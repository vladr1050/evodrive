<?php

namespace Tests\Feature;

use App\Enums\DriverStatus;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_dashboard_redirects_to_login(): void
    {
        $response = $this->get('/en/driverportal/dashboard');
        $response->assertRedirect('/en/driverportal');
    }

    public function test_unauthenticated_shifts_redirects_to_login(): void
    {
        $response = $this->get('/en/driverportal/shifts');
        $response->assertRedirect('/en/driverportal');
    }

    public function test_unauthenticated_profile_redirects_to_login(): void
    {
        $response = $this->get('/en/driverportal/profile');
        $response->assertRedirect('/en/driverportal');
    }

    public function test_authenticated_driver_sees_dashboard(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/dashboard');

        $response->assertStatus(200);
    }

    public function test_suspended_driver_cannot_log_in(): void
    {
        $driver = Driver::factory()->create([
            'status' => DriverStatus::Suspended,
        ]);

        $response = $this->post('/en/driverportal', [
            'email' => $driver->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/en/driverportal');
        $response->assertSessionHas('driverportal.error');
        $this->assertGuest('driver');
    }

    public function test_suspended_driver_session_is_rejected_on_portal_routes(): void
    {
        $driver = Driver::factory()->create(['status' => DriverStatus::Suspended]);

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/dashboard');

        $response->assertRedirect('/en/driverportal');
        $this->assertGuest('driver');
    }

    public function test_authenticated_driver_sees_shifts(): void
    {
        $driver = Driver::factory()->create();
        \App\Models\ShiftPolicy::factory()->create();
        \App\Models\Station::factory()->create();

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/shifts');

        $response->assertStatus(200);
    }

    public function test_driver_can_toggle_favorite_station(): void
    {
        $driver = Driver::factory()->create();
        $station = \App\Models\Station::factory()->create();

        $response = $this->actingAs($driver, 'driver')
            ->postJson('/en/driverportal/stations/toggle-favorite', [
                'station_id' => $station->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('is_favorite', true);

        $this->assertContains($station->id, $driver->fresh()->favoriteStationIds());
    }

    public function test_shifts_page_with_station_filter_loads(): void
    {
        $driver = Driver::factory()->create();
        \App\Models\ShiftPolicy::factory()->create();
        $station = \App\Models\Station::factory()->create([
            'provider' => 'Elektrum',
            'address' => 'Saharova iela 23a',
        ]);

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/shifts?station_id='.$station->id);

        $response->assertStatus(200);
        $this->assertSame($station->id, $driver->fresh()->recentStationIds()[0] ?? null);
    }

    public function test_free_slots_use_favorite_stations_when_no_station_filter(): void
    {
        $driver = Driver::factory()->create([
            'portal_preferences' => ['favorite_station_ids' => []],
        ]);
        \App\Models\ShiftPolicy::factory()->create([
            'min_duration_hours' => 4,
            'allowed_durations_json' => [4, 6, 8],
            'vehicle_downtime_hours' => 0,
            'time_slot_minutes' => 60,
            'timezone' => 'Europe/Riga',
        ]);

        $favA = \App\Models\Station::factory()->create(['name' => 'Fav A', 'is_active' => true]);
        $favB = \App\Models\Station::factory()->create(['name' => 'Fav B', 'is_active' => true]);
        $other = \App\Models\Station::factory()->create(['name' => 'Other', 'is_active' => true]);
        \App\Models\FleetVehicle::factory()->create(['home_station_id' => $favA->id]);
        \App\Models\FleetVehicle::factory()->create(['home_station_id' => $favB->id]);
        \App\Models\FleetVehicle::factory()->create(['home_station_id' => $other->id]);

        $driver->update([
            'portal_preferences' => [
                'favorite_station_ids' => [$favA->id, $favB->id],
            ],
        ]);

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/shifts');

        $response->assertOk();
        $this->assertFalse($response->viewData('shiftsPageInit')['requireStationForFree']);
        $slots = $response->viewData('availableSlots');
        $stationIds = collect($slots)->pluck('station_id')->unique()->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$favA->id, $favB->id], $stationIds);
        $this->assertNotContains($other->id, $stationIds);
    }

    public function test_free_slots_empty_without_favorites_or_station_filter(): void
    {
        $driver = Driver::factory()->create([
            'portal_preferences' => ['favorite_station_ids' => []],
        ]);
        \App\Models\ShiftPolicy::factory()->create();
        \App\Models\Station::factory()->create();

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/shifts');

        $response->assertOk();
        $this->assertTrue($response->viewData('shiftsPageInit')['requireStationForFree']);
        $this->assertSame([], $response->viewData('availableSlots'));
    }

    public function test_authenticated_driver_sees_profile(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->actingAs($driver, 'driver')
            ->get('/en/driverportal/profile');

        $response->assertStatus(200);
    }

    public function test_logout_invalidates_session_and_redirects(): void
    {
        $driver = Driver::factory()->create();

        $this->actingAs($driver, 'driver')->get('/en/driverportal/dashboard');

        $response = $this->post(route('driverportal.logout', ['locale' => 'en']), [
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect('/en/driverportal');

        $this->get('/en/driverportal/dashboard')
            ->assertRedirect('/en/driverportal');
    }
}
