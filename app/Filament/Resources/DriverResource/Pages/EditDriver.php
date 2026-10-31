<?php

namespace App\Filament\Resources\DriverResource\Pages;

use App\Filament\Actions\ExportDriverWorkedShiftsAction;
use App\Filament\Resources\DriverResource;
use App\Models\Driver;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportWorkedShifts')
                ->label('Export worked shifts')
                ->icon('heroicon-o-arrow-down-tray')
                ->form(ExportDriverWorkedShiftsAction::formSchema())
                ->action(function (array $data) {
                    /** @var Driver $driver */
                    $driver = $this->getRecord();

                    return ExportDriverWorkedShiftsAction::download($driver, $data);
                }),
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Model $record): void {
                    if ($record->shifts()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete')
                            ->body('Driver has linked shifts.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
