<?php

namespace App\Services\CarControl;

use App\Contracts\CarDeviceCommandTransportInterface;
use App\Contracts\SmsProviderInterface;

final class SmsCarDeviceTransport implements CarDeviceCommandTransportInterface
{
    public function __construct(
        protected SmsProviderInterface $smsProvider
    ) {}

    public function send(CarDeviceTransportSendRequest $request): CarDeviceCommandResult
    {
        $phone = $request->smsPhoneDigits ?? '';
        if ($phone === '') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'sms',
                error: 'SMS number missing',
                failureCode: 'sms_number_missing',
            );
        }

        $result = $this->smsProvider->send($phone, $request->commandText);
        $refs = [];
        if (isset($result['message_id'])) {
            $refs[] = (string) $result['message_id'];
        }

        if (($result['status'] ?? '') === 'failed') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'sms',
                providerRefs: $refs,
                error: $result['error'] ?? 'Send failed',
                failureCode: 'sms_send_failed',
            );
        }

        return new CarDeviceCommandResult(
            ok: true,
            transport: 'sms',
            providerRefs: $refs,
        );
    }
}
