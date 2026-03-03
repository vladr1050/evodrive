<?php

namespace App\Console\Commands;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

/**
 * Run on server to verify Telegram config and last cancelled shift.
 * Usage: php artisan telegram:notify-diagnostics
 */
class TelegramNotifyDiagnostics extends Command
{
    protected $signature = 'telegram:notify-diagnostics {--send : Send a test message to the group}';

    protected $description = 'Check Telegram config and why last cancelled shift may not have been notified';

    public function handle(): int
    {
        $token = config('telegram.bot_token');
        $chatId = config('telegram.shifts_chat_id');

        $this->line('TELEGRAM_BOT_TOKEN: ' . (strlen((string) $token) > 5 ? 'set (' . substr($token, 0, 10) . '...)' : 'EMPTY'));
        $this->line('TELEGRAM_SHIFTS_CHAT_ID: ' . ($chatId !== '' ? $chatId : 'EMPTY'));
        $this->line('Queue connection: ' . config('queue.default'));

        $last = Shift::where('status', ShiftStatus::Cancelled)
            ->orderBy('cancelled_at', 'desc')
            ->first();

        if (!$last) {
            $this->warn('No cancelled shifts in DB.');
            return $this->trySendTest($token, $chatId);
        }

        $this->line('--- Last cancelled shift ---');
        $this->line('id=' . $last->id . ' driver_id=' . $last->driver_id . ' station_id=' . $last->station_id);
        $this->line('cancelled_at=' . $last->cancelled_at . ' cancellation_notified_at=' . ($last->cancellation_notified_at ?? 'null'));

        $tolerance = config('telegram.replacement_tolerance_minutes', 15);
        $startMin = $last->starts_at->copy()->subMinutes($tolerance);
        $startMax = $last->starts_at->copy()->addMinutes($tolerance);
        $replacement = Shift::where('id', '!=', $last->id)
            ->where('driver_id', $last->driver_id)
            ->where('station_id', $last->station_id)
            ->where('status', ShiftStatus::Booked)
            ->whereBetween('starts_at', [$startMin, $startMax])
            ->exists();
        $this->line('Replacement shift exists (same slot): ' . ($replacement ? 'YES (would skip)' : 'no'));

        $max = config('telegram.cancellation_notify_max_per_driver', 3);
        $window = config('telegram.cancellation_notify_rate_window_minutes', 30);
        $count = Shift::where('driver_id', $last->driver_id)
            ->whereNotNull('cancellation_notified_at')
            ->where('cancellation_notified_at', '>=', now()->subMinutes($window))
            ->count();
        $this->line("Rate limit: {$count}/{$max} in last {$window} min " . ($count >= $max ? '(would skip)' : ''));

        return $this->trySendTest($token, $chatId);
    }

    private function trySendTest(?string $token, ?string $chatId): int
    {
        if (empty($token) || $chatId === '' || $chatId === null) {
            $this->error('Cannot send test: token or chat_id missing. Add TELEGRAM_* to .env and restart queue.');
            return 1;
        }

        if (!$this->option('send')) {
            $this->line('Run with --send to send a test message.');
            return 0;
        }

        $notifier = TelegramNotifier::fromConfig();
        $ok = $notifier->sendToShiftsChat('Evo.drive: тест уведомлений (diagnostics)');
        $this->line($ok ? 'Sent.' : 'Send failed — check storage/logs/laravel.log');
        return $ok ? 0 : 1;
    }
}
