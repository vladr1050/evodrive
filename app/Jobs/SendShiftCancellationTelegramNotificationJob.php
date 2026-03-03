<?php

namespace App\Jobs;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Services\TelegramNotifier;
use App\Models\ShiftPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a single shift-cancellation notification to the Telegram shifts chat
 * after anti-spam checks (still cancelled, no replacement shift, rate limit).
 */
class SendShiftCancellationTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Shift $shift
    ) {
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle(TelegramNotifier $notifier): void
    {
        $shift = $this->shift->fresh(['station']);
        if (!$shift) {
            Log::channel('stack')->warning('SendShiftCancellationTelegramNotificationJob: shift no longer exists', ['shift_id' => $this->shift->id]);
            return;
        }

        if ($shift->status !== ShiftStatus::Cancelled) {
            Log::channel('stack')->info('SendShiftCancellationTelegramNotificationJob: shift no longer cancelled, skipping', ['shift_id' => $shift->id]);
            return;
        }

        if ($shift->cancellation_notified_at !== null) {
            Log::channel('stack')->info('SendShiftCancellationTelegramNotificationJob: already notified, skipping', ['shift_id' => $shift->id]);
            return;
        }

        if ($this->replacementShiftExists($shift)) {
            Log::channel('stack')->info('SendShiftCancellationTelegramNotificationJob: replacement shift exists, skipping', ['shift_id' => $shift->id]);
            return;
        }

        $maxPerDriver = config('telegram.cancellation_notify_max_per_driver', 3);
        $windowMinutes = config('telegram.cancellation_notify_rate_window_minutes', 30);
        if ($this->driverExceedsRateLimit($shift, $maxPerDriver, $windowMinutes)) {
            Log::channel('stack')->warning('SendShiftCancellationTelegramNotificationJob: rate limit exceeded for driver', [
                'shift_id' => $shift->id,
                'driver_id' => $shift->driver_id,
            ]);
            return;
        }

        $tz = ShiftPolicy::active()?->timezone ?? config('app.timezone');
        $starts = $shift->starts_at->copy()->setTimezone($tz);
        $ends = $shift->ends_at->copy()->setTimezone($tz);
        $stationName = $shift->station?->name ?? '—';
        $stationAddress = $shift->station?->address ?? '';
        $text = "Station: {$stationName} — {$stationAddress}\n";
        $text .= 'Slot freed: ' . $starts->format('Y-m-d H:i') . '-' . $ends->format('H:i');

        $sent = $notifier->sendToShiftsChat($text);
        if ($sent) {
            $shift->update(['cancellation_notified_at' => now()]);
        }
    }

    /**
     * Check if a replacement booked shift exists: same driver, same station,
     * overlapping the cancelled window or within tolerance minutes.
     */
    protected function replacementShiftExists(Shift $cancelled): bool
    {
        $toleranceMinutes = config('telegram.replacement_tolerance_minutes', 15);
        $windowStart = $cancelled->starts_at->copy()->subMinutes($toleranceMinutes);
        $windowEnd = $cancelled->ends_at->copy()->addMinutes($toleranceMinutes);

        return Shift::query()
            ->where('id', '!=', $cancelled->id)
            ->where('driver_id', $cancelled->driver_id)
            ->where('station_id', $cancelled->station_id)
            ->where('status', ShiftStatus::Booked)
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->where('starts_at', '<', $windowEnd)
                        ->where('ends_at', '>', $windowStart);
                });
            })
            ->exists();
    }

    /**
     * Count how many cancellation notifications were sent for this driver
     * within the last window minutes. Returns true if at or over the limit.
     */
    protected function driverExceedsRateLimit(Shift $shift, int $maxPerDriver, int $windowMinutes): bool
    {
        $since = now()->subMinutes($windowMinutes);
        $count = Shift::query()
            ->where('driver_id', $shift->driver_id)
            ->whereNotNull('cancellation_notified_at')
            ->where('cancellation_notified_at', '>=', $since)
            ->count();

        return $count >= $maxPerDriver;
    }
}
