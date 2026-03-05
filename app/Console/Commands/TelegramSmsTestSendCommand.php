<?php

namespace App\Console\Commands;

use App\Contracts\SmsProviderInterface;
use Illuminate\Console\Command;

class TelegramSmsTestSendCommand extends Command
{
    protected $signature = 'telegram:sms-test-send
                            {to : Phone number (e.g. 37120000000)}
                            {--text= : SMS text (default: EvoDrive test)}';

    protected $description = 'Send one test SMS via NESS to verify provider is working';

    public function handle(SmsProviderInterface $sms): int
    {
        $to = $this->argument('to');
        $text = $this->option('text') ?? 'EvoDrive test';

        $this->info("Sending to {$to}: \"{$text}\"");

        $result = $sms->send($to, $text);

        if (($result['status'] ?? '') === 'sent') {
            $this->info('Sent. Message ID: ' . ($result['message_id'] ?? '—'));
            return self::SUCCESS;
        }

        $this->error('Failed: ' . ($result['error'] ?? 'Unknown'));
        return self::FAILURE;
    }
}
