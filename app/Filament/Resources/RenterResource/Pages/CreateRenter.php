<?php

namespace App\Filament\Resources\RenterResource\Pages;

use App\Filament\Resources\RenterResource;
use App\Filament\Resources\RenterResource\Pages\Concerns\SyncsRenterRentedFleetVehicles;
use Filament\Resources\Pages\CreateRecord;

class CreateRenter extends CreateRecord
{
    use SyncsRenterRentedFleetVehicles;

    protected static string $resource = RenterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['rented_fleet_vehicle_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncRenterRentedFleetVehicles($this->record);
    }
}
