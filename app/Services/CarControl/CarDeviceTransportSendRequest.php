<?php

namespace App\Services\CarControl;

/**
 * One logical command line (Teltonika text / former SMS body).
 */
final readonly class CarDeviceTransportSendRequest
{
    public function __construct(
        public string $commandText,
        public ?string $imei = null,
        public ?string $smsPhoneDigits = null,
        public int $timeoutSeconds = 30,
    ) {}
}
