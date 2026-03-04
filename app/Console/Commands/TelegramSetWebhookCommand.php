<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--url= : Webhook URL (default: APP_URL + /telegram/car-control-webhook)}';

    protected $description = 'Set Telegram bot webhook URL for car control (and other updates)';

    public function handle(): int
    {
        $token = config('telegram.bot_token');
        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $url = $this->option('url') ?? rtrim(config('app.url'), '/') . '/telegram/car-control-webhook';
        if (! str_starts_with($url, 'https://')) {
            $this->error('Webhook URL must be HTTPS. Set APP_URL in .env to your production URL.');
            return self::FAILURE;
        }

        $this->info("Setting webhook to: {$url}");

        $response = Http::get("https://api.telegram.org/bot{$token}/setWebhook", ['url' => $url]);

        $body = $response->json();
        if ($body['ok'] ?? false) {
            $this->info('Webhook set successfully.');
            return self::SUCCESS;
        }

        $this->error('Failed: ' . ($body['description'] ?? $response->body()));
        return self::FAILURE;
    }
}
