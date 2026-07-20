<?php

namespace App\Jobs;

use App\Services\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Notifies the Telegram shifts chat that staff removed a started (no-show) shift.
 * Payload is a snapshot — the shift row is hard-deleted before/when this runs.
 *
 * @phpstan-type NoShowPayload array{
 *     shift_id: int,
 *     station_name: string,
 *     station_address: string,
 *     vehicle_line: string,
 *     slot_line: string,
 *     driver_line: string,
 *     staff_line: string
 * }
 */
class SendShiftNoShowTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  NoShowPayload  $payload
     */
    public function __construct(
        public array $payload
    ) {
        $this->onQueue(config('queue.connections.database.queue', 'default'));
    }

    public function handle(TelegramNotifier $notifier): void
    {
        $p = $this->payload;
        $text = "No-show removed (vehicle freed)\n";
        $text .= 'Station: '.($p['station_name'] ?? '—').' — '.($p['station_address'] ?? '')."\n";
        if (($p['vehicle_line'] ?? '') !== '') {
            $text .= $p['vehicle_line']."\n";
        }
        $text .= ($p['slot_line'] ?? '')."\n";
        if (($p['driver_line'] ?? '') !== '') {
            $text .= $p['driver_line']."\n";
        }
        if (($p['staff_line'] ?? '') !== '') {
            $text .= $p['staff_line'];
        }

        $sent = $notifier->sendToShiftsChat(rtrim($text));
        if ($sent) {
            Log::channel('stack')->info('SendShiftNoShowTelegramNotificationJob: notification sent', [
                'shift_id' => $p['shift_id'] ?? null,
            ]);
        }
    }
}
