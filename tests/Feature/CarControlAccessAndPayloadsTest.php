<?php

namespace Tests\Feature;

use App\Contracts\SmsProviderInterface;
use App\Enums\ShiftStatus;
use App\Models\CarCommand;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\Station;
use App\Services\CarControlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Car control: access window, SMS payloads per action, rate limit, idempotency.
 */
class CarControlAccessAndPayloadsTest extends TestCase
{
    use RefreshDatabase;

    protected Driver $driver;

    protected FleetVehicle $vehicle;

    protected Station $station;

    /** @var array<int, array{to: string, text: string}> */
    protected array $smsLog = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->station = Station::factory()->create();
        $this->vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $this->station->id,
            'sim' => '37120000001',
        ]);
        $this->driver = Driver::factory()->create();

        $this->smsLog = [];
        $fake = new class($this->smsLog) implements SmsProviderInterface
        {
            public function __construct(
                protected array &$log
            ) {}

            public function send(string $to, string $text): array
            {
                $this->log[] = ['to' => $to, 'text' => $text];

                return ['message_id' => 'test-'.count($this->log), 'status' => 'sent'];
            }
        };
        $this->app->instance(SmsProviderInterface::class, $fake);
        $this->app->forgetInstance(CarControlService::class);
    }

    public function test_access_allowed_when_now_in_window(): void
    {
        $now = Carbon::parse('2026-03-10 11:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy(),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $context = $service->getDriverCarControlContext($this->driver->id, $now);

        $this->assertTrue($context['allowed']);
        $this->assertArrayHasKey('shift', $context);
        $this->assertArrayHasKey('vehicle', $context);
    }

    public function test_access_denied_too_early(): void
    {
        $now = Carbon::parse('2026-03-10 08:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addHours(2),
            'ends_at' => $now->copy()->addHours(6),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $context = $service->getDriverCarControlContext($this->driver->id, $now);

        $this->assertFalse($context['allowed']);
        $this->assertSame('too_early', $context['reason']);
    }

    public function test_access_denied_too_late(): void
    {
        $now = Carbon::parse('2026-03-10 20:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->subHours(6),
            'ends_at' => $now->copy()->subHours(2),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $context = $service->getDriverCarControlContext($this->driver->id, $now);

        $this->assertFalse($context['allowed']);
        $this->assertSame('too_late', $context['reason']);
    }

    public function test_access_denied_no_shift(): void
    {
        $service = app(CarControlService::class);
        $context = $service->getDriverCarControlContext($this->driver->id, now());

        $this->assertFalse($context['allowed']);
        $this->assertSame('no_shift', $context['reason']);
    }

    public function test_access_denied_car_not_configured(): void
    {
        $v = FleetVehicle::factory()->create(['home_station_id' => $this->station->id, 'sim' => null]);
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $v->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $context = $service->getDriverCarControlContext($this->driver->id, $now);

        $this->assertFalse($context['allowed']);
        $this->assertSame('car_not_configured', $context['reason']);
    }

    public function test_start_shift_sends_two_sms_in_order_unlock_then_open(): void
    {
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_START_SHIFT, $now);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $this->smsLog);
        $unlock = config('car_control.commands.unlock_engine', 'youto youto setdigout 00 0 0');
        $open = config('car_control.commands.open_car', 'youto youto lvcanopenalldoors');
        $this->assertSame($unlock, $this->smsLog[0]['text']);
        $this->assertSame($open, $this->smsLog[1]['text']);
        $this->assertSame('37120000001', $this->smsLog[0]['to']);
        $this->assertSame('37120000001', $this->smsLog[1]['to']);
    }

    public function test_open_car_sends_one_sms(): void
    {
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_OPEN_CAR, $now);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->smsLog);
        $open = config('car_control.commands.open_car', 'youto youto lvcanopenalldoors');
        $this->assertSame($open, $this->smsLog[0]['text']);
    }

    public function test_close_car_sends_one_sms(): void
    {
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_CLOSE_CAR, $now);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->smsLog);
        $close = config('car_control.commands.close_car', 'youto youto lvcanclosealldoors');
        $this->assertSame($close, $this->smsLog[0]['text']);
    }

    public function test_end_shift_sends_two_sms_in_order_lock_then_close(): void
    {
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->subMinutes(30),
            'ends_at' => $now->copy()->addMinutes(30),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_END_SHIFT, $now);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $this->smsLog);
        $lock = config('car_control.commands.lock_engine', 'youto youto setdigout 10 0 0');
        $close = config('car_control.commands.close_car', 'youto youto lvcanclosealldoors');
        $this->assertSame($lock, $this->smsLog[0]['text']);
        $this->assertSame($close, $this->smsLog[1]['text']);
    }

    public function test_rate_limit_blocks_second_command_within_driver_window(): void
    {
        config(['car_control.rate_limit_driver_seconds' => 15]);
        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $service = app(CarControlService::class);

        $first = $service->executeAction($this->driver->id, CarCommand::ACTION_OPEN_CAR, $now);
        $this->assertTrue($first['ok']);

        $second = $service->executeAction($this->driver->id, CarCommand::ACTION_CLOSE_CAR, $now);

        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('in progress', $second['message']);
        $this->assertCount(1, $this->smsLog);
    }

    public function test_idempotency_returns_in_progress_when_queued_command_exists(): void
    {
        $now = Carbon::parse('2026-03-10 10:00:00');
        $shift = Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        CarCommand::create([
            'driver_id' => $this->driver->id,
            'shift_id' => $shift->id,
            'vehicle_id' => $this->vehicle->id,
            'action' => CarCommand::ACTION_OPEN_CAR,
            'sms_to' => $this->vehicle->sim,
            'sms_payloads' => ['youto youto lvcanopenalldoors'],
            'status' => CarCommand::STATUS_QUEUED,
        ]);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_OPEN_CAR, $now);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('in progress', $result['message']);
        $this->assertCount(0, $this->smsLog);
    }

    public function test_open_car_uses_gprs_gateway_when_transport_is_gprs(): void
    {
        Http::fake([
            'http://teltonika-gw.test/commands' => Http::response(['ok' => true, 'request_id' => 'gw-1'], 200),
        ]);
        config([
            'car_control.default_transport' => 'gprs',
            'car_control.gprs.internal_base_url' => 'http://teltonika-gw.test',
            'car_control.gprs.commands_path' => 'commands',
        ]);
        $this->vehicle->update(['imei' => '123456789012345']);

        $now = Carbon::parse('2026-03-10 10:00:00');
        Shift::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'station_id' => $this->station->id,
            'starts_at' => $now->copy()->addMinutes(30),
            'ends_at' => $now->copy()->addHours(4),
            'status' => ShiftStatus::Booked,
        ]);
        $this->app->forgetInstance(CarControlService::class);
        $service = app(CarControlService::class);

        $result = $service->executeAction($this->driver->id, CarCommand::ACTION_OPEN_CAR, $now);

        $this->assertTrue($result['ok']);
        $this->assertCount(0, $this->smsLog);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://teltonika-gw.test/commands'
                && $request['imei'] === '123456789012345'
                && $request['command'] === config('car_control.commands.open_car');
        });
    }
}
