<?php

namespace App\Contracts;

use App\Services\CarControl\CarDeviceCommandResult;
use App\Services\CarControl\CarDeviceTransportSendRequest;

interface CarDeviceCommandTransportInterface
{
    public function send(CarDeviceTransportSendRequest $request): CarDeviceCommandResult;
}
