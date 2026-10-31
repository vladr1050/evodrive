<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Models\User;
use App\Services\DriverWorkedShiftsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverWorkedShiftsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ShiftPolicy::factory()->create([
            'timezone' => 'Europe/Riga',
            'planning_window_days' => 14,
        ]);
    }

    public function test_report_lists_completed_shifts_and_totals(): void
    {
        $tz = 'Europe/Riga';
        $driver = Driver::factory()->create(['first_name' => 'Janis', 'last_name' => 'Berzins']);
        $other = Driver::factory()->create();
        $station = Station::factory()->create(['name' => 'Test Station']);
        $vehicle = FleetVehicle::factory()->create([
            'home_station_id' => $station->id,
            'registration_number' => 'EX-1001',
        ]);

        Shift::factory()->create([
            'driver_id' => $driver->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'starts_at' => Carbon::parse('2026-09-01 08:00:00', $tz)->utc(),
            'ends_at' => Carbon::parse('2026-09-01 16:00:00', $tz)->utc(),
            'status' => ShiftStatus::Completed,
            'started_via_bot_at' => Carbon::parse('2026-09-01 08:05:00', $tz)->utc(),
        ]);
        Shift::factory()->create([
            'driver_id' => $driver->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'starts_at' => Carbon::parse('2026-09-02 10:00:00', $tz)->utc(),
            'ends_at' => Carbon::parse('2026-09-02 14:00:00', $tz)->utc(),
            'status' => ShiftStatus::Booked,
        ]);
        Shift::factory()->create([
            'driver_id' => $other->id,
            'station_id' => $station->id,
            'vehicle_id' => $vehicle->id,
            'starts_at' => Carbon::parse('2026-09-01 08:00:00', $tz)->utc(),
            'ends_at' => Carbon::parse('2026-09-01 12:00:00', $tz)->utc(),
            'status' => ShiftStatus::Completed,
        ]);

        $report = app(DriverWorkedShiftsReportService::class)->build(
            $driver,
            '2026-09-01',
            '2026-09-30',
            false
        );

        $this->assertSame(1, $report['shift_count']);
        $this->assertSame(8.0, $report['total_hours']);
        $this->assertSame('EX-1001', $report['shifts'][0]['vehicle']);
        $this->assertSame('completed', $report['shifts'][0]['status']);
        $this->assertNotNull($report['shifts'][0]['started_via_bot_at']);

        $withBooked = app(DriverWorkedShiftsReportService::class)->build(
            $driver,
            '2026-09-01',
            '2026-09-30',
            true
        );
        $this->assertSame(2, $withBooked['shift_count']);
        $this->assertSame(12.0, $withBooked['total_hours']);
    }

    public function test_pdf_view_renders_for_report(): void
    {
        $driver = Driver::factory()->create(['first_name' => 'Anna', 'last_name' => 'Ozola']);
        $report = app(DriverWorkedShiftsReportService::class)->build(
            $driver,
            '2026-09-01',
            '2026-09-30',
            false
        );

        $pdf = Pdf::loadView('filament.reports.driver-worked-shifts', ['report' => $report]);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF', $output);
    }

    public function test_admin_can_open_driver_edit_page_with_export_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-worked-shifts@test.com',
        ]);
        $driver = Driver::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/drivers/'.$driver->id.'/edit')
            ->assertOk()
            ->assertSee('Export worked shifts');
    }
}
