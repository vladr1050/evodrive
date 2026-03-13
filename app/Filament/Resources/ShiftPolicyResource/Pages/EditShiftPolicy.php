<?php

namespace App\Filament\Resources\ShiftPolicyResource\Pages;

use App\Filament\Resources\ShiftPolicyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShiftPolicy extends EditRecord
{
    protected static string $resource = ShiftPolicyResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['allowed_durations_json']) && is_array($data['allowed_durations_json'])) {
            $data['allowed_durations_json'] = array_values(array_map(
                fn ($h) => ['hours' => (int) $h],
                array_filter($data['allowed_durations_json'], fn ($v) => is_numeric($v))
            ));
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['allowed_durations_json']) && is_array($data['allowed_durations_json'])) {
            $hours = [];
            foreach ($data['allowed_durations_json'] as $row) {
                if (isset($row['hours']) && is_numeric($row['hours'])) {
                    $hours[] = (int) $row['hours'];
                }
            }
            $data['allowed_durations_json'] = array_values(array_unique($hours));
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
