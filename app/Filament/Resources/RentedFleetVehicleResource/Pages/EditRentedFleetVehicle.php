<?php

namespace App\Filament\Resources\RentedFleetVehicleResource\Pages;

use App\Filament\Resources\RentedFleetVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRentedFleetVehicle extends EditRecord
{
    protected static string $resource = RentedFleetVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
