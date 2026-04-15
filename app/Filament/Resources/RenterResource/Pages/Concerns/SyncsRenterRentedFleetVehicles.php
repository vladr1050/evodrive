<?php

namespace App\Filament\Resources\RenterResource\Pages\Concerns;

use App\Models\RentedFleetVehicle;
use App\Models\Renter;

trait SyncsRenterRentedFleetVehicles
{
    protected function syncRenterRentedFleetVehicles(Renter $renter): void
    {
        $ids = collect($this->form->getState()['rented_fleet_vehicle_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        RentedFleetVehicle::query()
            ->where('renter_id', $renter->id)
            ->whereNotIn('id', $ids)
            ->update(['renter_id' => null]);

        if ($ids !== []) {
            RentedFleetVehicle::query()->whereIn('id', $ids)->update(['renter_id' => $renter->id]);
        }
    }
}
