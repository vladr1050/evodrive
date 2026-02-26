<?php

namespace App\Filament\Resources\ShiftPolicyResource\Pages;

use App\Filament\Resources\ShiftPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShiftPolicy extends EditRecord
{
    protected static string $resource = ShiftPolicyResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['allowed_durations_json']) && is_array($data['allowed_durations_json'])) {
            $data['allowed_durations_json'] = array_map('intval', $data['allowed_durations_json']);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
