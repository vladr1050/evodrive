<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Resources\FleetVehicleResource;
use App\Filament\Resources\FleetVehicleServiceBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFleetVehicle extends EditRecord
{
    protected static string $resource = FleetVehicleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['label'] = trim(($data['brand'] ?? '').' '.($data['model'] ?? '').' ('.($data['registration_number'] ?? '').')');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('schedule_service')
                ->label('Schedule service')
                ->icon('heroicon-o-wrench-screwdriver')
                ->url(fn (): string => FleetVehicleServiceBlockResource::getUrl('create', [
                    'fleet_vehicle_id' => $this->record->getKey(),
                ])),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Model $record): void {
                    if ($record->shifts()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete')
                            ->body('Vehicle has linked shifts.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
