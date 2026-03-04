<?php

namespace App\Contracts;

interface SmsProviderInterface
{
    /**
     * Send one SMS to the given number.
     *
     * @param  string  $to  Phone number (international format without +, e.g. 37120000000)
     * @param  string  $text  Message content (UTF-8)
     * @return array{message_id: string|null, status: string, error?: string}
     */
    public function send(string $to, string $text): array;
}
