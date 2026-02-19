<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\Page;

class ListSiteSettings extends Page
{
    protected static string $resource = SiteSettingResource::class;

    protected static string $view = 'filament.resources.site-setting-resource.pages.list-site-settings';

    public function mount(): void
    {
        $setting = SiteSetting::first();
        if (! $setting) {
            SiteSetting::create(['logo_path' => null, 'favicon_path' => null]);
        }
        $this->redirect(SiteSettingResource::getUrl('edit', ['record' => SiteSetting::first()->id]));
    }
}
