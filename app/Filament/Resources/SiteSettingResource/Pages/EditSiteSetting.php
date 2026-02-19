<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\EditRecord;

class EditSiteSetting extends EditRecord
{
    protected static string $resource = SiteSettingResource::class;

    public function mount(int|string|null $record = null): void
    {
        $setting = SiteSetting::first();
        if (! $setting) {
            SiteSetting::create(['logo_path' => null, 'favicon_path' => null]);
        }
        parent::mount((string) SiteSetting::first()->id);
    }

    public static function getRoutePath(): string
    {
        return '/{record}';
    }
}
