<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NESS / Sales.lv SMS API 0.16 (https://help.ness-solutions.com/en/article/using-http-api-version-016-current-1qaogrt/)
 * Sends via POST with APIKey, Command=Send, Recipients, Sender, Content.
 */
class NessSmsProvider implements SmsProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $senderId,
        protected string $baseUrl
    ) {
    }

    public function send(string $to, string $text): array
    {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['message_id' => null, 'status' => 'failed', 'error' => 'Invalid recipient number'];
        }

        if ($this->apiKey === '') {
            Log::channel('stack')->warning('NessSmsProvider: NESS_SMS_API_KEY not set');
            return ['message_id' => null, 'status' => 'failed', 'error' => 'SMS provider not configured'];
        }

        $url = rtrim($this->baseUrl, '/');
        $response = Http::timeout(15)->asForm()->post($url, [
            'APIKey' => $this->apiKey,
            'Command' => 'Send',
            'Recipients' => $to,
            'Sender' => $this->senderId,
            'Content' => $text,
        ]);

        if (! $response->successful()) {
            Log::channel('stack')->error('NessSmsProvider: request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['message_id' => null, 'status' => 'failed', 'error' => 'SMS gateway error'];
        }

        $json = $response->json();
        if (isset($json['Error'])) {
            Log::channel('stack')->warning('NessSmsProvider: API error', ['error' => $json['Error'], 'response' => $json]);

            return ['message_id' => null, 'status' => 'failed', 'error' => $json['Error']];
        }

        // Success: response is array of messages, one entry per recipient
        $entry = is_array($json) && isset($json[0]) ? $json[0] : $json;
        $messageId = $entry['ID'] ?? null;
        $invalid = $entry['Invalid'] ?? null;
        if ($invalid) {
            return ['message_id' => null, 'status' => 'failed', 'error' => 'Invalid: ' . $invalid];
        }

        return ['message_id' => (string) $messageId, 'status' => 'sent'];
    }

    public static function fromConfig(): self
    {
        return new self(
            config('sms.ness.api_key', ''),
            config('sms.ness.sender_id', ''),
            config('sms.ness.base_url', '')
        );
    }
}
