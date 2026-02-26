<?php

namespace App\Filament\Resources\StationResource\Pages;

use App\Filament\Resources\StationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStation extends CreateRecord
{
    protected static string $resource = StationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['slug']) || $data['slug'] === '') {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name'] ?? 'station');
        }
        return $data;
    }
}
