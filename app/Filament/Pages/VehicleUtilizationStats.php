<?php

namespace App\Filament\Pages;

use App\Models\ShiftPolicy;
use App\Services\VehicleUtilizationStatsService;
use Filament\Pages\Page;

class VehicleUtilizationStats extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Statistics';

    protected static ?string $navigationLabel = 'Vehicle utilization';

    protected static ?int $navigationSort = 50;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.vehicle-utilization-stats';

    protected static ?string $title = 'Vehicle utilization';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    protected function getViewData(): array
    {
        $tz = ShiftPolicy::active()?->timezone ?? 'Europe/Riga';
        $rows = app(VehicleUtilizationStatsService::class)->getDailyUtilization($tz);

        return [
            'rows' => $rows,
        ];
    }
}
