<?php

namespace App\Filament\Pages;

use App\Models\FleetVehicle;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\Utilization\DateRange;
use App\Services\Utilization\UtilizationFilters;
use App\Services\Utilization\VehicleUtilizationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleetUtilization extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Statistics';

    protected static ?string $navigationLabel = 'Utilization';

    protected static ?int $navigationSort = 15;

    protected static string $view = 'filament.pages.fleet-utilization';

    protected static ?string $title = 'Fleet utilization';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** @var array<int> */
    public array $vehicleIds = [];

    /** @var array<int> */
    public array $stationIds = [];

    public string $statusMode = UtilizationFilters::STATUS_MODE_BOTH;

    public bool $showIntervalModal = false;

    public ?int $intervalModalVehicleId = null;

    public ?string $intervalModalDate = null;

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
        $filters = new UtilizationFilters(
            $this->vehicleIds ?: null,
            $this->stationIds ?: null,
            $this->statusMode,
            $tz
        );
        $service = app(VehicleUtilizationService::class);
        $rows = $service->getDailyUtilization($range, $filters);

        $dateKeys = $range->dateKeys();
        $vehicles = $this->getVehicleList($rows);
        $heatmap = $this->buildHeatmap($rows, $vehicles, $dateKeys);
        $kpis = $this->computeKpis($rows, $vehicles->count(), count($dateKeys));

        $intervalDetail = [];
        $intervalModalVehicleName = null;
        if ($this->showIntervalModal && $this->intervalModalVehicleId && $this->intervalModalDate) {
            $intervalDetail = $service->getDailyIntervals($this->intervalModalVehicleId, $this->intervalModalDate, $filters);
            $v = FleetVehicle::find($this->intervalModalVehicleId);
            $intervalModalVehicleName = $v ? ($v->registration_number . ' ' . $v->brand . ' ' . $v->model) : '';
        }

        return [
            'rows' => $rows,
            'dateKeys' => $dateKeys,
            'vehicles' => $vehicles,
            'heatmap' => $heatmap,
            'kpis' => $kpis,
            'vehiclesSelect' => FleetVehicle::orderBy('registration_number')->get(['id', 'brand', 'model', 'registration_number']),
            'stationsSelect' => Station::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'intervalDetail' => $intervalDetail,
            'intervalModalVehicleName' => $intervalModalVehicleName,
        ];
    }

    public function openIntervalModal(int $vehicleId, string $date): void
    {
        $this->intervalModalVehicleId = $vehicleId;
        $this->intervalModalDate = $date;
        $this->showIntervalModal = true;
    }

    public function closeIntervalModal(): void
    {
        $this->showIntervalModal = false;
        $this->intervalModalVehicleId = null;
        $this->intervalModalDate = null;
    }

    protected function getVehicleList(Collection $rows): Collection
    {
        $byVehicle = $rows->unique('vehicle_id')->sortBy('vehicle_name')->values();
        return $byVehicle->map(fn ($r) => (object) ['id' => $r->vehicle_id, 'name' => $r->vehicle_name])->values();
    }

    protected function buildHeatmap(Collection $rows, Collection $vehicles, array $dateKeys): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[$r->vehicle_id][$r->date] = (object) [
                'total_hours' => $r->total_hours,
                'booked_hours' => round($r->booked_minutes / 60, 1),
                'completed_hours' => round($r->completed_minutes / 60, 1),
                'total_minutes' => $r->total_minutes,
            ];
        }
        return $map;
    }

    protected function computeKpis(Collection $rows, int $vehicleCount, int $dayCount): object
    {
        $totalVehicleDays = $vehicleCount * max(1, $dayCount);
        $avgHours = $totalVehicleDays > 0
            ? round($rows->sum(fn ($r) => $r->total_minutes) / 60 / $totalVehicleDays, 2)
            : 0;
        $fleetRate = $avgHours / 24;
        $peakHours = $rows->isEmpty() ? 0 : round($rows->max(fn ($r) => $r->total_minutes) / 60, 1);
        $vehiclesWithLessThan1h = $rows->filter(fn ($r) => $r->total_minutes < 60)->pluck('vehicle_id')->unique()->count();

        return (object) [
            'avg_hours' => $avgHours,
            'fleet_rate' => round($fleetRate * 100, 1),
            'peak_hours' => $peakHours,
            'idle_vehicles' => $vehiclesWithLessThan1h,
        ];
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
        $filters = new UtilizationFilters($this->vehicleIds ?: null, $this->stationIds ?: null, $this->statusMode, $tz);
        $rows = app(VehicleUtilizationService::class)->getDailyUtilization($range, $filters);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'vehicle_id', 'vehicle_name', 'booked_hours', 'completed_hours', 'total_hours']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->date,
                    $r->vehicle_id,
                    $r->vehicle_name,
                    round($r->booked_minutes / 60, 2),
                    round($r->completed_minutes / 60, 2),
                    $r->total_hours,
                ]);
            }
            fclose($out);
        }, 'fleet-utilization-' . $this->dateFrom . '-to-' . $this->dateTo . '.csv', [
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
