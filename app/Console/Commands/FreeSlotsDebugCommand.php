<?php

namespace App\Console\Commands;

use App\Services\ShiftAvailabilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Debug why free slots are missing for some days (e.g. today).
 * Run: php artisan slots:debug
 * Or:  php artisan slots:debug "Ignitis Jaunmoku"
 */
class FreeSlotsDebugCommand extends Command
{
    protected $signature = 'slots:debug {station? : Optional station name (partial match) or ID to filter}';

    protected $description = 'Show free intervals and slot counts per day for the current week (debug missing slots)';

    public function handle(ShiftAvailabilityService $service): int
    {
        $policy = \App\Models\ShiftPolicy::active();
        if (! $policy) {
            $this->warn('No active shift policy.');
            return self::FAILURE;
        }

        $tz = $policy->timezone ?? 'Europe/Riga';
        $now = now($tz);
        $dayOfWeek = $now->dayOfWeek;
        $diffToMonday = $dayOfWeek === 0 ? -6 : 1 - $dayOfWeek;
        $startOfWeek = $now->copy()->addDays($diffToMonday)->startOfDay();
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $filterStationId = null;
        $stationArg = $this->argument('station');
        if ($stationArg) {
            if (is_numeric($stationArg)) {
                $filterStationId = (int) $stationArg;
            } else {
                $station = \App\Models\Station::where('is_active', true)
                    ->where('name', 'like', '%' . $stationArg . '%')
                    ->first();
                if ($station) {
                    $filterStationId = $station->id;
                    $this->line('Filtering by station: ' . $station->name . ' (ID ' . $station->id . ')');
                } else {
                    $this->warn('Station not found: ' . $stationArg);
                }
            }
        }

        $debug = $service->getFreeSlotsDebug($startOfWeek, $dayNames, $filterStationId);

        $this->newLine();
        $this->info('Policy: min_duration_hours=' . ($debug['policy']['min_duration_hours'] ?? '?')
            . ', allowed_durations=' . json_encode($debug['policy']['allowed_durations'] ?? [])
            . ', time_slot_minutes=' . ($debug['policy']['time_slot_minutes'] ?? '?')
            . ', downtime_hours=' . ($debug['policy']['vehicle_downtime_hours'] ?? '?')
            . ', timezone=' . ($debug['policy']['timezone'] ?? '?'));
        $this->line('Now (tz): ' . $debug['now']);
        $this->line('Week start: ' . $debug['week_start']);
        $this->newLine();

        foreach ($debug['days'] as $day) {
            $skipTag = $day['skipped'] ? ' [SKIPPED - past]' : '';
            $this->line($day['date'] . ' ' . $day['name'] . $skipTag . ' — slots: ' . $day['slots_count']);
            foreach ($day['stations'] as $st) {
                $intervalsStr = empty($st['intervals']) ? 'none' : implode(', ', array_map(fn ($iv) => $iv[0] . '-' . $iv[1], $st['intervals']));
                $this->line('  • ' . $st['station_name'] . ': intervals [' . $intervalsStr . '] → ' . $st['slots_count'] . ' slot(s)');
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
