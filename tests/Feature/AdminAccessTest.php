<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_redirects_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_admin_can_access_panel(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin');

        $response->assertStatus(200);
    }

    public function test_manager_can_access_panel(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($manager)
            ->get('/admin');

        $response->assertStatus(200);
    }

    /**
     * Smoke test: admin can load all key resource index pages.
     * Asserts 200 or expected redirect (e.g. shift-policies, site-settings redirect to edit).
     * No CRUD testing.
     */
    #[DataProvider('adminResourceIndexRoutesProvider')]
    public function test_admin_can_load_resource_index_pages(string $path, bool $allowsRedirect = false): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($admin)->get($path);
        if ($allowsRedirect && $response->status() === 302) {
            $location = $response->headers->get('Location') ?? '';
            $this->assertStringNotContainsString('login', $location, "Unexpected redirect to login for: {$path}");

            return;
        }
        $response->assertOk("Failed for path: {$path}");
    }

    public static function adminResourceIndexRoutesProvider(): array
    {
        return [
            'admin_dashboard' => ['/admin', false],
            'admin_users' => ['/admin/users', false],
            'admin_drivers' => ['/admin/drivers', false],
            'admin_stations' => ['/admin/stations', false],
            'admin_fleet_vehicles' => ['/admin/fleet-vehicles', false],
            'admin_shifts' => ['/admin/shifts', false],
            'admin_shift_vehicle_reassignment' => ['/admin/shift-vehicle-reassignment', false],
            'admin_shift_policies' => ['/admin/shift-policies', true], // Redirects to edit when single policy
            'admin_leads' => ['/admin/leads', false],
            'admin_rental_vehicles' => ['/admin/rental-vehicles', false],
            'admin_renters' => ['/admin/renters', false],
            'admin_rented_fleet_vehicles' => ['/admin/rented-fleet-vehicles', false],
            'admin_pages' => ['/admin/pages', false],
            'admin_faq_categories' => ['/admin/faq-categories', false],
            'admin_translations' => ['/admin/translations', false],
            'admin_site_settings' => ['/admin/site-settings', true], // Redirects to edit when single setting
        ];
    }
}
