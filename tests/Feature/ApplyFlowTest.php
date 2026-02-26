<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\RentalVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\CreatesMinimalAppData;
use Tests\TestCase;

class ApplyFlowTest extends TestCase
{
    use CreatesMinimalAppData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalAppData();
    }

    public function test_apply_page_returns_200(): void
    {
        $response = $this->get('/en/apply');
        $response->assertStatus(200);
    }

    public function test_valid_apply_creates_lead_with_expected_fields(): void
    {
        $this->get('/en/apply');
        $payload = [
            'phone' => '+37121234567',
            'intent' => 'work',
            'atd_license' => 'yes',
            'driving_experience' => '5-10',
            'name' => 'John Doe',
            'area' => 'Riga',
            '_token' => csrf_token(),
        ];

        $response = $this->post('/en/apply', $payload);

        $response->assertRedirect();
        $this->assertStringContainsString('thanks', $response->headers->get('Location'));

        $lead = Lead::where('phone', '+37121234567')->first();
        $this->assertNotNull($lead);
        $this->assertSame('work', $lead->intent);
        $this->assertTrue($lead->atd_license);
        $this->assertSame('5-10', $lead->driving_experience);
        $this->assertSame('John Doe', $lead->name);
        $this->assertSame('Riga', $lead->area);
        $this->assertNotNull($lead->ip_address);
        $this->assertNotNull($lead->user_agent);
    }

    public function test_honeypot_website_url_filled_returns_422(): void
    {
        $this->get('/en/apply');
        $payload = [
            'phone' => '+37121234567',
            'intent' => 'work',
            'atd_license' => 'yes',
            'driving_experience' => '5-10',
            'name' => 'John Doe',
            'area' => 'Riga',
            'website_url' => 'https://spam.com',
            '_token' => csrf_token(),
        ];

        $response = $this->post('/en/apply', $payload);

        $response->assertSessionHasErrors();
        $this->assertSame(0, Lead::count());
    }

    public function test_throttle_exceeds_10_per_minute_returns_429(): void
    {
        $ip = '192.168.100.1';
        $key = sha1('|' . $ip);

        RateLimiter::clear($key);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($key, 60);
        }

        $this->get('/en/apply');
        $payload = [
            'phone' => '+37129999999',
            'intent' => 'work',
            'atd_license' => 'yes',
            'driving_experience' => '5-10',
            'name' => 'John Doe',
            'area' => 'Riga',
            '_token' => csrf_token(),
        ];

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/en/apply', $payload);

        $response->assertStatus(429);
    }

    public function test_utm_params_are_captured(): void
    {
        $this->get('/en/apply');
        $payload = [
            'phone' => '+37121234567',
            'intent' => 'work',
            'atd_license' => 'yes',
            'driving_experience' => '5-10',
            'name' => 'John Doe',
            'area' => 'Riga',
            'utm_source' => 'google',
            'utm_campaign' => 'test-campaign',
            'utm_medium' => 'cpc',
            '_token' => csrf_token(),
        ];

        $this->post('/en/apply', $payload);

        $lead = Lead::where('phone', '+37121234567')->first();
        $this->assertNotNull($lead);
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('test-campaign', $lead->utm_campaign);
        $this->assertSame('cpc', $lead->utm_medium);
    }

    public function test_rent_detail_prefill_stores_rent_car_id_and_intent(): void
    {
        $rental = RentalVehicle::factory()->create(['is_active' => true]);
        $this->get('/en/apply');

        $payload = [
            'phone' => '+37121234567',
            'intent' => 'rent',
            'rent_car_id' => (string) $rental->id,
            'atd_license' => 'yes',
            'driving_experience' => '10+',
            'name' => 'Jane Doe',
            'area' => 'Riga',
            '_token' => csrf_token(),
        ];

        $this->post('/en/apply', $payload);

        $lead = Lead::where('phone', '+37121234567')->first();
        $this->assertNotNull($lead);
        $this->assertSame('rent', $lead->intent);
        $this->assertSame($rental->id, $lead->rent_car_id);
    }
}
