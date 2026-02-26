<?php

namespace App\Filament\Resources\StationResource\Pages;

use App\Filament\Resources\StationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStation extends EditRecord
{
    protected static string $resource = StationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Model $record): void {
                    if ($record->fleetVehicles()->exists() || $record->shifts()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete')
                            ->body('Station has linked vehicles or shifts.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
