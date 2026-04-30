<?php

namespace Tests\Concerns;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\SiteSetting;

trait CreatesMinimalAppData
{
    protected function createMinimalAppData(): void
    {
        SiteSetting::firstOrCreate([], [
            'logo_path' => null,
            'favicon_path' => null,
        ]);

        $pages = [
            ['key' => 'google_landing', 'title' => ['en' => 'Work', 'lv' => 'Darbs', 'ru' => 'Работа'], 'slug' => ['en' => 'g', 'lv' => 'g', 'ru' => 'g'], 'meta_title' => [], 'is_active' => true],
            ['key' => 'meta_landing', 'title' => ['en' => 'Career', 'lv' => 'Karjera', 'ru' => 'Карьера'], 'slug' => ['en' => 'm', 'lv' => 'm', 'ru' => 'm'], 'meta_title' => [], 'is_active' => true],
        ];

        foreach ($pages as $data) {
            $page = Page::firstOrCreate(['key' => $data['key']], $data);
            PageSection::firstOrCreate(
                ['page_id' => $page->id, 'key' => 'hero'],
                ['content' => ['en' => ['h1' => 'Test'], 'lv' => ['h1' => 'Test'], 'ru' => ['h1' => 'Тест']], 'sort_order' => 1]
            );
        }

        $legalSeed = require database_path('data/legal_sections_seed.php');
        foreach (['privacy', 'terms'] as $legalKey) {
            $page = Page::firstOrCreate(
                ['key' => $legalKey],
                [
                    'title' => match ($legalKey) {
                        'privacy' => ['en' => 'Privacy Policy', 'ru' => 'Политика', 'lv' => 'Privātums'],
                        'terms' => ['en' => 'Terms', 'ru' => 'Условия', 'lv' => 'Noteikumi'],
                        default => ['en' => 'Legal', 'ru' => 'Legal', 'lv' => 'Legal'],
                    },
                    'slug' => ['en' => $legalKey, 'ru' => $legalKey, 'lv' => $legalKey],
                    'meta_title' => ['en' => $legalKey, 'ru' => $legalKey, 'lv' => $legalKey],
                    'is_active' => true,
                ]
            );
            foreach ($legalSeed[$legalKey] as $sectionData) {
                PageSection::firstOrCreate(
                    ['page_id' => $page->id, 'key' => $sectionData['key']],
                    ['content' => $sectionData['content'], 'sort_order' => $sectionData['sort_order']]
                );
            }
        }
    }
}
