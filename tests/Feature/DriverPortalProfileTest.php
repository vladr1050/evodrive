<?php

namespace Tests\Feature;

use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverPortalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_persists_atd_number(): void
    {
        $driver = Driver::factory()->create([
            'name' => 'Test Driver',
            'atd_number' => null,
        ]);

        $this->actingAs($driver, 'driver')->get(route('driverportal.profile', ['locale' => 'en']));

        $response = $this->actingAs($driver, 'driver')
            ->post(route('driverportal.profile.update', ['locale' => 'en']), [
                '_token' => csrf_token(),
                'name' => 'Test Driver',
                'phone' => '+371 12345678',
                'atd_number' => 'ATD-12345',
            ]);

        $response->assertRedirect();
        $driver->refresh();
        $this->assertSame('ATD-12345', $driver->atd_number);
        $this->assertSame('+371 12345678', $driver->phone);
    }
}
