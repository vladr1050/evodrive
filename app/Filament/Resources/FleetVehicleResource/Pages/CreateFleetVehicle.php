<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Resources\FleetVehicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFleetVehicle extends CreateRecord
{
    protected static string $resource = FleetVehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['label'])) {
            $data['label'] = trim(($data['brand'] ?? '') . ' ' . ($data['model'] ?? '') . ' (' . ($data['registration_number'] ?? '') . ')');
        }
        return $data;
    }
}
