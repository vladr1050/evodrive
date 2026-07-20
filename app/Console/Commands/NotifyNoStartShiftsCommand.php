<?php

namespace App\Console\Commands;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Notify the shifts Telegram chat when a booked/completed shift has no
 * successful Start shift (bot) after the configured grace period.
 */
class NotifyNoStartShiftsCommand extends Command
{
    protected $signature = 'shifts:notify-no-start';

    protected $description = 'Telegram-notify when a driver has not pressed Start shift within the grace period after shift start.';

    public function handle(TelegramNotifier $notifier): int
    {
        $graceMinutes = (int) config('telegram.no_start_grace_minutes', 60);
        $lookbackHours = (int) config('telegram.no_start_lookback_hours', 24);
        $cutoff = now()->subMinutes($graceMinutes);
        $oldest = now()->subHours($lookbackHours);

        $shifts = Shift::query()
            ->with(['station', 'driver', 'vehicle', 'originalVehicle'])
            ->whereIn('status', [ShiftStatus::Booked, ShiftStatus::Completed])
            ->whereNull('started_via_bot_at')
            ->whereNull('no_start_notified_at')
            ->where('starts_at', '<=', $cutoff)
            ->where('starts_at', '>=', $oldest)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        if ($shifts->isEmpty()) {
            $this->info('No late-start shifts to notify.');

            return self::SUCCESS;
        }

        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone');
        $sent = 0;

        foreach ($shifts as $shift) {
            $text = $this->buildMessage($shift, $tz, $graceMinutes);
            if (! $notifier->sendToShiftsChat($text)) {
                Log::channel('stack')->warning('NotifyNoStartShiftsCommand: Telegram send failed', [
                    'shift_id' => $shift->id,
                ]);

                continue;
            }

            $shift->update(['no_start_notified_at' => now()]);
            $sent++;
            Log::channel('stack')->info('NotifyNoStartShiftsCommand: notification sent', [
                'shift_id' => $shift->id,
            ]);
        }

        $this->info("Sent {$sent} late-start notification(s).");

        return self::SUCCESS;
    }

    private function buildMessage(Shift $shift, string $tz, int $graceMinutes): string
    {
        $starts = $shift->starts_at->copy()->setTimezone($tz);
        $ends = $shift->ends_at->copy()->setTimezone($tz);
        $stationName = $shift->station?->name ?? '—';
        $stationAddress = $shift->station?->address ?? '';
        $driverName = $shift->driver?->name ?? '—';

        $vehicle = $shift->originalVehicle ?? $shift->vehicle;
        $vehicleLine = '';
        if ($vehicle) {
            $plate = trim((string) $vehicle->registration_number);
            $label = trim((string) $vehicle->label);
            if ($plate !== '') {
                $vehicleLine = "Vehicle: {$plate}\n";
            } elseif ($label !== '') {
                $vehicleLine = "Vehicle: {$label}\n";
            }
        }

        $text = "Driver did not start shift (no Start shift in bot after {$graceMinutes} min)\n";
        $text .= "Station: {$stationName} — {$stationAddress}\n";
        $text .= $vehicleLine;
        $text .= 'Shift: '.$starts->format('Y-m-d H:i').'-'.$ends->format('H:i')."\n";
        $text .= "Driver: {$driverName}";

        return $text;
    }
}
