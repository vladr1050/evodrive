<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends plain-text messages to a Telegram chat via the Bot API.
 * Used for shift cancellation notifications (and optionally other alerts).
 */
class TelegramNotifier
{
    public function __construct(
        protected string $botToken,
        protected string $shiftsChatId
    ) {
    }

    /**
     * Send a plain text message to the configured shifts notification chat.
     *
     * @return bool True if the API returned ok, false otherwise (logged).
     */
    public function sendToShiftsChat(string $text): bool
    {
        if ($this->botToken === '' || $this->shiftsChatId === '') {
            Log::channel('stack')->warning('TelegramNotifier: TELEGRAM_BOT_TOKEN or TELEGRAM_SHIFTS_CHAT_ID not set, skipping send.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $response = Http::timeout(10)->post($url, [
            'chat_id' => $this->shiftsChatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);

        if (!$response->successful()) {
            Log::channel('stack')->error('TelegramNotifier: sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $json = $response->json();
        if (!($json['ok'] ?? false)) {
            Log::channel('stack')->error('TelegramNotifier: API returned not ok', ['response' => $json]);
            return false;
        }

        return true;
    }

    /**
     * Create notifier from config (for use in jobs and app binding).
     */
    public static function fromConfig(): self
    {
        return new self(
            config('telegram.bot_token', ''),
            config('telegram.shifts_chat_id', '')
        );
    }
}
