<?php

namespace App\Filament\Resources\RentedFleetVehicleResource\Pages;

use App\Filament\Resources\RentedFleetVehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRentedFleetVehicle extends CreateRecord
{
    protected static string $resource = RentedFleetVehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['label'])) {
            $data['label'] = trim(($data['brand'] ?? '').' '.($data['model'] ?? '').' ('.($data['registration_number'] ?? '').')');
        }

        return $data;
    }
}
