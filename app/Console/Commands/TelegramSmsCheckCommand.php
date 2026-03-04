<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TelegramSmsCheckCommand extends Command
{
    protected $signature = 'telegram:sms-check';

    protected $description = 'Check if SMS (NESS) config is visible to the app (for car control)';

    public function handle(): int
    {
        $key = config('sms.ness.api_key', '');
        $sender = config('sms.ness.sender_id', '');
        $baseUrl = config('sms.ness.base_url', '');

        $keyOk = $key !== '';
        $this->line('NESS_SMS_API_KEY: ' . ($keyOk ? 'set (' . strlen($key) . ' chars)' : 'EMPTY'));
        $this->line('NESS_SMS_SENDER_ID: ' . ($sender !== '' ? $sender : 'empty'));
        $this->line('NESS_SMS_BASE_URL: ' . ($baseUrl !== '' ? $baseUrl : 'empty'));

        if (! $keyOk) {
            $this->error('SMS provider will report "not configured". Fix: add NESS_SMS_API_KEY to .env, then config:clear, config:cache, and restart app/queue containers.');
            return self::FAILURE;
        }

        $this->info('SMS config looks OK. If bot still fails, restart containers: docker compose restart app queue');
        return self::SUCCESS;
    }
}
