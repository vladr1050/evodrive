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
            $hours = array_values(array_unique(array_map('intval', array_filter($data['allowed_durations_json'], fn ($v) => is_numeric($v)))));
            $data['allowed_durations_json'] = array_values(array_filter($hours, fn ($h) => $h >= 1 && $h <= 24));
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
