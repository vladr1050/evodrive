<?php

namespace App\Listeners;

use App\Events\ShiftCancelled;
use App\Jobs\SendShiftCancellationTelegramNotificationJob;

/**
 * Schedules the debounced Telegram notification job (3-minute delay)
 * after a shift is cancelled (driver portal or staff in Filament).
 */
class SendShiftCancellationTelegramNotification
{
    public int $delayMinutes = 3;

    public function handle(ShiftCancelled $event): void
    {
        SendShiftCancellationTelegramNotificationJob::dispatch($event->shift)
            ->delay(now()->addMinutes($this->delayMinutes));
    }
}
