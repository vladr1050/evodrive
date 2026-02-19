<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGroup('ui', lang_path('en/ui.php'), lang_path('ru/ui.php'), lang_path('lv/ui.php'));
        $this->seedGroup('apply', lang_path('en/apply.php'), lang_path('ru/apply.php'), lang_path('lv/apply.php'));
    }

    private function seedGroup(string $group, string $enPath, string $ruPath, string $lvPath): void
    {
        $en = file_exists($enPath) ? (include $enPath) : [];
        $ru = file_exists($ruPath) ? (include $ruPath) : [];
        $lv = file_exists($lvPath) ? (include $lvPath) : [];

        $keys = array_unique(array_merge(array_keys($en), array_keys($ru), array_keys($lv)));

        foreach ($keys as $key) {
            Translation::firstOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'en' => $en[$key] ?? null,
                    'ru' => $ru[$key] ?? null,
                    'lv' => $lv[$key] ?? null,
                ]
            );
        }
    }
}
