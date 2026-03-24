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
