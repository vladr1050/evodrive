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
     * Send a message to a specific chat (e.g. driver's telegram_id). Optional inline keyboard.
     *
     * @param  string|int  $chatId  Telegram chat_id
     * @param  array|null  $replyMarkup  InlineKeyboardMarkup: ['inline_keyboard' => [[['text'=>'Btn','callback_data'=>'data']]]]
     * @return bool True if the API returned ok, false otherwise (logged).
     */
    public function sendToChat(string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        if ($this->botToken === '') {
            Log::channel('stack')->warning('TelegramNotifier: TELEGRAM_BOT_TOKEN not set, skipping send.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        $response = Http::timeout(10)->post($url, $payload);

        if (! $response->successful()) {
            Log::channel('stack')->error('TelegramNotifier: sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            Log::channel('stack')->error('TelegramNotifier: API returned not ok', ['response' => $json]);
            return false;
        }

        return true;
    }

    /**
     * Answer a callback_query (e.g. after button press) to remove loading state.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): bool
    {
        if ($this->botToken === '') {
            return false;
        }
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        $response = Http::timeout(5)->post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", $payload);

        return $response->successful() && ($response->json()['ok'] ?? false);
    }

    /**
     * Edit message text (e.g. after button press to show result).
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
    {
        if ($this->botToken === '') {
            return false;
        }
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        $response = Http::timeout(5)->post("https://api.telegram.org/bot{$this->botToken}/editMessageText", $payload);

        return $response->successful() && ($response->json()['ok'] ?? false);
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

        return $this->sendToChat($this->shiftsChatId, $text);

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
