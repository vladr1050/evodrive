<?php

namespace App\Filament\Resources\RentedFleetVehicleResource\Pages;

use App\Filament\Resources\RentedFleetVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRentedFleetVehicles extends ListRecords
{
    protected static string $resource = RentedFleetVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
