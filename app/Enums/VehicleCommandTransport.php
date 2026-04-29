<?php

namespace App\Enums;

enum VehicleCommandTransport: string
{
    case Sms = 'sms';
    case Gprs = 'gprs';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Gprs => 'GPRS',
            self::Auto => 'Auto (GPRS if online, else SMS)',
        };
    }
}
