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

        $result = $this->smsProvider->send($phone, $this->smsBodyForDeviceCommand($request->commandText));
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

    /**
     * SMS uses Teltonika SMS login/password prefix; GPRS sends {@see $bareCommand} unchanged.
     */
    private function smsBodyForDeviceCommand(string $bareCommand): string
    {
        $bareCommand = trim($bareCommand);
        $prefix = trim((string) config('car_control.sms.command_prefix', 'youto youto'));
        if ($prefix === '') {
            return $bareCommand;
        }

        return $bareCommand === '' ? $prefix : $prefix.' '.$bareCommand;
    }
}
