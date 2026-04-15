<?php

namespace App\Filament\Resources\RenterResource\Pages;

use App\Filament\Resources\RenterResource;
use App\Filament\Resources\RenterResource\Pages\Concerns\SyncsRenterRentedFleetVehicles;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRenter extends EditRecord
{
    use SyncsRenterRentedFleetVehicles;

    protected static string $resource = RenterResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['rented_fleet_vehicle_ids'] = $this->record->rentedFleetVehicles()->pluck('id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['rented_fleet_vehicle_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncRenterRentedFleetVehicles($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
