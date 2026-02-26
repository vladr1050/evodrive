<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMinimalAppData;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use CreatesMinimalAppData;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalAppData();
    }

    #[DataProvider('localeProvider')]
    public function test_public_pages_return_200_for_locale(string $locale): void
    {
        $routes = [
            "/{$locale}",
            "/{$locale}/g",
            "/{$locale}/m",
            "/{$locale}/rent",
            "/{$locale}/faq",
            "/{$locale}/privacy",
            "/{$locale}/terms",
        ];

        foreach ($routes as $path) {
            $response = $this->followingRedirects()->get($path);
            $response->assertStatus(200, "Failed for path: {$path}");
        }
    }

    public function test_rent_detail_returns_200_when_vehicle_exists(): void
    {
        $vehicle = \App\Models\RentalVehicle::factory()->create(['is_active' => true]);

        $response = $this->get("/en/rent/{$vehicle->id}");
        $response->assertStatus(200);
    }

    public function test_rent_detail_returns_404_when_vehicle_not_found(): void
    {
        $response = $this->get('/en/rent/99999');
        $response->assertStatus(404);
    }

    public function test_sitemap_returns_200_and_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');

        $xml = @simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap must be valid XML');
        $this->assertNotNull($xml->url);
    }

    public function test_robots_txt_contains_disallow_admin_and_sitemap(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Disallow: /admin', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }

    public static function localeProvider(): array
    {
        return [
            'en' => ['en'],
            'lv' => ['lv'],
            'ru' => ['ru'],
        ];
    }
}
