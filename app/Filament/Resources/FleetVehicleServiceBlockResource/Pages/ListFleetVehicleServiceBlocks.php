<?php

namespace App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;

use App\Filament\Resources\FleetVehicleServiceBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFleetVehicleServiceBlocks extends ListRecords
{
    protected static string $resource = FleetVehicleServiceBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
