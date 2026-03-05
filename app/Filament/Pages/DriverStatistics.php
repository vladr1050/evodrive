<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\FleetVehicle;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\DriverUtilizationFilters;
use App\Services\Utilization\DriverUtilizationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverStatistics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Statistics';

    protected static ?string $navigationLabel = 'Driver Statistics';

    protected static ?int $navigationSort = 16;

    protected static string $view = 'filament.pages.driver-statistics';

    protected static ?string $title = 'Driver Statistics';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** @var array<int> */
    public array $driverIds = [];

    /** @var array<int> */
    public array $stationIds = [];

    /** @var array<int> */
    public array $vehicleIds = [];

    public string $statusMode = DriverUtilizationFilters::STATUS_MODE_BOTH;

    public bool $showBreakdownModal = false;

    public ?int $breakdownDriverId = null;

    public ?string $breakdownDate = null;

    public function mount(): void
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays(13);
        if ($this->dateFrom === null) {
            $this->dateFrom = $start->format('Y-m-d');
        }
        if ($this->dateTo === null) {
            $this->dateTo = $end->format('Y-m-d');
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    protected function getViewData(): array
    {
        $tz = ShiftPolicy::active()?->timezone ?? 'Europe/Riga';
        $range = new DateRange($this->dateFrom, $this->dateTo);
        $filters = new DriverUtilizationFilters(
            $this->driverIds ?: null,
            $this->stationIds ?: null,
            $this->vehicleIds ?: null,
            $this->statusMode,
            $tz
        );
        $service = app(DriverUtilizationService::class);
        $rows = $service->getDailyDriverUtilization($range, $filters);

        $dateKeys = $range->dateKeys();
        $drivers = $this->getDriverList($rows);
        $heatmap = $this->buildHeatmap($rows, $drivers, $dateKeys);
        $kpis = $this->computeKpis($rows, $drivers->count(), count($dateKeys));
        $stationBreakdown = $this->computeStationBreakdown($rows);
        $vehicleBreakdown = $this->computeVehicleBreakdown($rows);

        $breakdownDetail = [];
        $breakdownDriverName = null;
        if ($this->showBreakdownModal && $this->breakdownDriverId && $this->breakdownDate) {
            $breakdownDetail = $service->getDriverDayBreakdown($this->breakdownDriverId, $this->breakdownDate, $filters);
            $d = Driver::find($this->breakdownDriverId);
            $breakdownDriverName = $d ? $d->name : '';
        }

        return [
            'rows' => $rows,
            'dateKeys' => $dateKeys,
            'drivers' => $drivers,
            'heatmap' => $heatmap,
            'kpis' => $kpis,
            'stationBreakdown' => $stationBreakdown,
            'vehicleBreakdown' => $vehicleBreakdown,
            'driversSelect' => Driver::orderBy('name')->get(['id', 'name']),
            'stationsSelect' => Station::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vehiclesSelect' => FleetVehicle::orderBy('registration_number')->get(['id', 'brand', 'model', 'registration_number']),
            'breakdownDetail' => $breakdownDetail,
            'breakdownDriverName' => $breakdownDriverName,
        ];
    }

    public function openBreakdownModal(int $driverId, string $date): void
    {
        $this->breakdownDriverId = $driverId;
        $this->breakdownDate = $date;
        $this->showBreakdownModal = true;
    }

    public function closeBreakdownModal(): void
    {
        $this->showBreakdownModal = false;
        $this->breakdownDriverId = null;
        $this->breakdownDate = null;
    }

    protected function getDriverList(Collection $rows): Collection
    {
        $byDriver = $rows->unique('driver_id')->sortBy('driver_name')->values();

        return $byDriver->map(fn ($r) => (object) ['id' => $r->driver_id, 'name' => $r->driver_name])->values();
    }

    protected function buildHeatmap(Collection $rows, Collection $drivers, array $dateKeys): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r->driver_id][$r->date] = (object) [
                'total_hours' => $r->total_hours,
                'planned_hours' => round($r->planned_minutes / 60, 1),
                'worked_hours' => round($r->worked_minutes / 60, 1),
                'total_minutes' => $r->total_minutes,
                'stations' => $r->stations ?? [],
                'vehicles' => $r->vehicles ?? [],
            ];
        }

        return $map;
    }

    protected function computeKpis(Collection $rows, int $driverCount, int $dayCount): object
    {
        $plannedHours = round($rows->sum(fn ($r) => $r->planned_minutes) / 60, 1);
        $workedHours = round($rows->sum(fn ($r) => $r->worked_minutes) / 60, 1);
        $totalHours = round($rows->sum(fn ($r) => $r->total_minutes) / 60, 1);
        $completionRate = $plannedHours > 0 ? round($workedHours / $plannedHours * 100, 1) : 0;
        $activeDrivers = $rows->pluck('driver_id')->unique()->count();
        $driverDays = $driverCount * max(1, $dayCount);
        $avgHoursPerDriverPerDay = $driverDays > 0 ? round($totalHours / $driverDays, 2) : 0;

        return (object) [
            'planned_hours' => $plannedHours,
            'worked_hours' => $workedHours,
            'completion_rate' => $completionRate,
            'active_drivers' => $activeDrivers,
            'avg_hours_per_driver_per_day' => $avgHoursPerDriverPerDay,
        ];
    }

    protected function computeStationBreakdown(Collection $rows): array
    {
        $byStation = [];
        foreach ($rows as $r) {
            $stations = $r->stations ?? [];
            $n = max(1, count($stations));
            $planned = ($r->planned_minutes / 60) / $n;
            $worked = ($r->worked_minutes / 60) / $n;
            foreach ($stations as $stationName) {
                if (! isset($byStation[$stationName])) {
                    $byStation[$stationName] = ['planned_hours' => 0, 'worked_hours' => 0, 'drivers' => [], 'vehicles' => []];
                }
                $byStation[$stationName]['planned_hours'] += $planned;
                $byStation[$stationName]['worked_hours'] += $worked;
                if (! in_array($r->driver_name, $byStation[$stationName]['drivers'], true)) {
                    $byStation[$stationName]['drivers'][] = $r->driver_name;
                }
                foreach ($r->vehicles ?? [] as $v) {
                    if (! in_array($v, $byStation[$stationName]['vehicles'], true)) {
                        $byStation[$stationName]['vehicles'][] = $v;
                    }
                }
            }
        }
        $out = [];
        foreach ($byStation as $name => $data) {
            $out[] = (object) [
                'station' => $name,
                'planned_hours' => round($data['planned_hours'], 1),
                'worked_hours' => round($data['worked_hours'], 1),
                'drivers_count' => count($data['drivers']),
                'vehicles_used' => count($data['vehicles']),
            ];
        }
        usort($out, fn ($a, $b) => $b->worked_hours <=> $a->worked_hours);

        return $out;
    }

    protected function computeVehicleBreakdown(Collection $rows): array
    {
        $byVehicle = [];
        foreach ($rows as $r) {
            $vehicles = $r->vehicles ?? [];
            $n = max(1, count($vehicles));
            $planned = ($r->planned_minutes / 60) / $n;
            $worked = ($r->worked_minutes / 60) / $n;
            foreach ($vehicles as $vehicleLabel) {
                if (! isset($byVehicle[$vehicleLabel])) {
                    $byVehicle[$vehicleLabel] = ['planned_hours' => 0, 'worked_hours' => 0, 'drivers' => []];
                }
                $byVehicle[$vehicleLabel]['planned_hours'] += $planned;
                $byVehicle[$vehicleLabel]['worked_hours'] += $worked;
                if (! in_array($r->driver_name, $byVehicle[$vehicleLabel]['drivers'], true)) {
                    $byVehicle[$vehicleLabel]['drivers'][] = $r->driver_name;
                }
            }
        }
        $out = [];
        foreach ($byVehicle as $label => $data) {
            $out[] = (object) [
                'vehicle' => $label,
                'planned_hours' => round($data['planned_hours'], 1),
                'worked_hours' => round($data['worked_hours'], 1),
                'drivers_count' => count($data['drivers']),
            ];
        }
        usort($out, fn ($a, $b) => $b->worked_hours <=> $a->worked_hours);

        return $out;
    }

    public function setQuickRange(string $range): void
    {
        $today = Carbon::today();
        switch ($range) {
            case 'last7':
                $this->dateFrom = $today->copy()->subDays(6)->format('Y-m-d');
                $this->dateTo = $today->format('Y-m-d');
                break;
            case 'last14':
                $this->dateFrom = $today->copy()->subDays(13)->format('Y-m-d');
                $this->dateTo = $today->format('Y-m-d');
                break;
            case 'last30':
                $this->dateFrom = $today->copy()->subDays(29)->format('Y-m-d');
                $this->dateTo = $today->format('Y-m-d');
                break;
            case 'next7':
                $this->dateFrom = $today->format('Y-m-d');
                $this->dateTo = $today->copy()->addDays(6)->format('Y-m-d');
                break;
            case 'next14':
                $this->dateFrom = $today->format('Y-m-d');
                $this->dateTo = $today->copy()->addDays(13)->format('Y-m-d');
                break;
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $range = new DateRange($this->dateFrom, $this->dateTo);
        $tz = ShiftPolicy::active()?->timezone ?? 'Europe/Riga';
        $filters = new DriverUtilizationFilters(
            $this->driverIds ?: null,
            $this->stationIds ?: null,
            $this->vehicleIds ?: null,
            $this->statusMode,
            $tz
        );
        $rows = app(DriverUtilizationService::class)->getDailyDriverUtilization($range, $filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'driver_id', 'driver_name', 'station', 'vehicle', 'planned_hours', 'worked_hours', 'total_hours']);
            foreach ($rows as $r) {
                $station = implode(', ', $r->stations ?? []);
                $vehicle = implode(', ', $r->vehicles ?? []);
                fputcsv($out, [
                    $r->date,
                    $r->driver_id,
                    $r->driver_name,
                    $station,
                    $vehicle,
                    round($r->planned_minutes / 60, 2),
                    round($r->worked_minutes / 60, 2),
                    $r->total_hours,
                ]);
            }
            fclose($out);
        }, 'driver-statistics-' . $this->dateFrom . '-to-' . $this->dateTo . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->action('exportCsv')
                ->icon('heroicon-o-arrow-down-tray'),
        ];
    }
}
