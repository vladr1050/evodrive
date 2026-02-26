<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMinimalAppData;
use Tests\TestCase;

class LocalizationSmokeTest extends TestCase
{
    use CreatesMinimalAppData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalAppData();
    }

    public function test_locale_routes_exist_and_return_200(): void
    {
        $locales = ['en', 'lv', 'ru'];

        foreach ($locales as $locale) {
            $response = $this->get("/{$locale}");
            $response->assertStatus(200);
        }
    }

    public function test_driver_portal_login_page_loads_for_each_locale(): void
    {
        foreach (['en', 'lv', 'ru'] as $locale) {
            $response = $this->get("/{$locale}/driverportal");
            $response->assertStatus(200);
        }
    }

    public function test_apply_page_loads_for_each_locale(): void
    {
        foreach (['en', 'lv', 'ru'] as $locale) {
            $response = $this->get("/{$locale}/apply");
            $response->assertStatus(200);
        }
    }
}
