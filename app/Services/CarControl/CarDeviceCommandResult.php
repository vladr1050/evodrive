<?php

namespace App\Services\CarControl;

/**
 * Outcome of sending one device command string over a transport channel.
 */
final readonly class CarDeviceCommandResult
{
    /**
     * @param  array<int, string>  $providerRefs
     */
    public function __construct(
        public bool $ok,
        public string $transport,
        public array $providerRefs = [],
        public ?string $error = null,
        public ?string $failureCode = null,
    ) {}

    public function allowsSmsFallback(): bool
    {
        if ($this->ok || $this->transport !== 'gprs') {
            return false;
        }

        return in_array($this->failureCode, [
            'timeout',
            'connection_lost',
            'device_offline',
            'gateway_unreachable',
            'gateway_not_configured',
        ], true);
    }
}
