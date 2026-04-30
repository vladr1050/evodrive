<?php

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $legalSeed = require database_path('data/legal_sections_seed.php');

        foreach (['privacy', 'terms'] as $pageKey) {
            $page = Page::query()->where('key', $pageKey)->first();
            if (! $page || ! isset($legalSeed[$pageKey])) {
                continue;
            }

            foreach ($legalSeed[$pageKey] as $sectionData) {
                $key = $sectionData['key'];
                if ($page->sections()->where('key', $key)->exists()) {
                    continue;
                }

                PageSection::query()->create([
                    'page_id' => $page->id,
                    'key' => $key,
                    'sort_order' => $sectionData['sort_order'] ?? 1,
                    'content' => $sectionData['content'],
                ]);
            }
        }
    }
};
