<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Jobs\SendShiftCancellationTelegramNotificationJob;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\TelegramNotifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShiftCancellationTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected ShiftPolicy $policy;
    protected Station $station;
    protected FleetVehicle $vehicle;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.bot_token' => 'test-token']);
        config(['telegram.shifts_chat_id' => '-1001234567890']);
        $this->policy = ShiftPolicy::factory()->create([
            'timezone' => 'Europe/Riga',
        ]);
        $this->station = Station::factory()->create([
            'name' => 'Central Station',
            'address' => '123 Main St',
        ]);
        $this->vehicle = FleetVehicle::factory()->create(['home_station_id' => $this->station->id]);
        $this->driver = Driver::factory()->create();
    }

    public function test_no_replacement_shift_sends_telegram_and_sets_cancellation_notified_at(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHours(4);
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => $this->driver->id,
            'cancel_reason' => 'cancelled_by_driver',
            'cancellation_notified_at' => null,
        ]);

        $job = new SendShiftCancellationTelegramNotificationJob($shift);
        $job->handle(TelegramNotifier::fromConfig());

        $shift->refresh();
        $this->assertNotNull($shift->cancellation_notified_at);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return str_contains($request->url(), 'sendMessage')
                && ($body['chat_id'] ?? '') !== ''
                && str_contains($body['text'] ?? '', 'Central Station')
                && str_contains($body['text'] ?? '', '123 Main St')
                && str_contains($body['text'] ?? '', 'Slot freed:');
        });
    }

    public function test_replacement_shift_exists_telegram_not_sent(): void
    {
        Http::fake();

        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHours(4);
        $cancelled = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => $this->driver->id,
            'cancellation_notified_at' => null,
        ]);

        // Replacement: same driver, same station, booked, overlapping (same window)
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => ShiftStatus::Booked,
        ]);

        $job = new SendShiftCancellationTelegramNotificationJob($cancelled);
        $job->handle(TelegramNotifier::fromConfig());

        $cancelled->refresh();
        $this->assertNull($cancelled->cancellation_notified_at);
        Http::assertNothingSent();
    }

    public function test_rate_limit_skips_notification(): void
    {
        Http::fake();

        config(['telegram.cancellation_notify_max_per_driver' => 3]);
        config(['telegram.cancellation_notify_rate_window_minutes' => 30]);

        // Three shifts already notified in the last 30 minutes for this driver
        for ($i = 0; $i < 3; $i++) {
            Shift::factory()->create([
                'driver_id' => $this->driver->id,
                'vehicle_id' => $this->vehicle->id,
                'station_id' => $this->station->id,
                'starts_at' => Carbon::tomorrow()->addHours($i)->setTime(8, 0),
                'ends_at' => Carbon::tomorrow()->addHours($i)->setTime(12, 0),
                'status' => ShiftStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_notified_at' => now()->subMinutes(5),
            ]);
        }

        $fourth = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => Carbon::tomorrow()->setTime(14, 0),
            'ends_at' => Carbon::tomorrow()->setTime(18, 0),
            'status' => ShiftStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_driver_id' => $this->driver->id,
            'cancellation_notified_at' => null,
        ]);

        $job = new SendShiftCancellationTelegramNotificationJob($fourth);
        $job->handle(TelegramNotifier::fromConfig());

        $fourth->refresh();
        $this->assertNull($fourth->cancellation_notified_at);
        Http::assertNothingSent();
    }
}
