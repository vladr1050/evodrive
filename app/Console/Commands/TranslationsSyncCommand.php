<?php

namespace App\Console\Commands;

use App\Models\Translation;
use Illuminate\Console\Command;

/**
 * Sync translation strings from lang/*.php (ui, apply) into the translations table.
 * Run after adding or changing keys in lang/en/ui.php, lang/en/apply.php, etc.
 * Values in DB take precedence on the site; this command only inserts missing rows.
 */
class TranslationsSyncCommand extends Command
{
    protected $signature = 'translations:sync';

    protected $description = 'Sync UI and Apply translations from lang files to database (for Filament editing).';

    public function handle(): int
    {
        $count = 0;
        $count += $this->syncGroup('ui', lang_path('en/ui.php'), lang_path('ru/ui.php'), lang_path('lv/ui.php'));
        $count += $this->syncGroup('apply', lang_path('en/apply.php'), lang_path('ru/apply.php'), lang_path('lv/apply.php'));

        $this->info("Synced {$count} translation key(s).");

        return self::SUCCESS;
    }

    private function syncGroup(string $group, string $enPath, string $ruPath, string $lvPath): int
    {
        $en = file_exists($enPath) ? (include $enPath) : [];
        $ru = file_exists($ruPath) ? (include $ruPath) : [];
        $lv = file_exists($lvPath) ? (include $lvPath) : [];

        $keys = array_unique(array_merge(array_keys($en), array_keys($ru), array_keys($lv)));
        $added = 0;

        foreach ($keys as $key) {
            $existed = Translation::where('group', $group)->where('key', $key)->exists();
            Translation::firstOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'en' => $en[$key] ?? null,
                    'ru' => $ru[$key] ?? null,
                    'lv' => $lv[$key] ?? null,
                ]
            );
            if (! $existed) {
                $added++;
            }
        }

        $this->line("  {$group}: " . count($keys) . " keys" . ($added > 0 ? " ({$added} new)" : ''));

        return count($keys);
    }
}
