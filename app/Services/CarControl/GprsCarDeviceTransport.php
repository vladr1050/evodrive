<?php

namespace App\Services\CarControl;

use App\Contracts\CarDeviceCommandTransportInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends commands to the Teltonika TCP gateway over HTTP (internal network).
 * Gateway contract (MVP): see config/car_control.php → gprs section.
 */
final class GprsCarDeviceTransport implements CarDeviceCommandTransportInterface
{
    public function send(CarDeviceTransportSendRequest $request): CarDeviceCommandResult
    {
        $base = rtrim((string) config('car_control.gprs.internal_base_url', ''), '/');
        if ($base === '') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: 'GPRS gateway URL not configured',
                failureCode: 'gateway_not_configured',
            );
        }

        $imei = $request->imei ?? '';
        if ($imei === '') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: 'IMEI missing',
                failureCode: 'device_offline',
            );
        }

        $token = config('car_control.gprs.internal_token');
        $timeout = max(1, $request->timeoutSeconds);
        $url = $base.'/'.ltrim((string) config('car_control.gprs.commands_path', 'commands'), '/');

        try {
            $pending = Http::timeout($timeout)
                ->acceptJson()
                ->asJson();
            if (filled($token)) {
                $pending = $pending->withToken((string) $token);
            }
            $response = $pending->post($url, [
                'imei' => $imei,
                'command' => $request->commandText,
                'timeout_seconds' => $timeout,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::channel('stack')->warning('GprsCarDeviceTransport: connection error', [
                'imei' => $imei,
                'message' => $e->getMessage(),
            ]);

            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: 'Gateway unreachable',
                failureCode: 'gateway_unreachable',
            );
        } catch (\Throwable $e) {
            Log::channel('stack')->error('GprsCarDeviceTransport: unexpected error', [
                'imei' => $imei,
                'message' => $e->getMessage(),
            ]);

            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: $e->getMessage(),
                failureCode: 'connection_lost',
            );
        }

        if ($response->status() === 408 || $response->json('failure_code') === 'timeout') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: $response->json('error') ?? 'Device response timeout',
                failureCode: 'timeout',
            );
        }

        if ($response->successful()) {
            $ok = (bool) $response->json('ok', $response->json('success', false));
            if ($ok) {
                $refs = [];
                $id = $response->json('request_id') ?? $response->json('id');
                if ($id !== null) {
                    $refs[] = (string) $id;
                }

                return new CarDeviceCommandResult(
                    ok: true,
                    transport: 'gprs',
                    providerRefs: $refs,
                );
            }

            $code = (string) ($response->json('failure_code') ?? $response->json('error_code') ?? 'command_failed');

            return new CarDeviceCommandResult(
                ok: false,
                transport: 'gprs',
                error: (string) ($response->json('error') ?? $response->json('message') ?? 'Command failed'),
                failureCode: $code !== '' ? $code : 'command_failed',
            );
        }

        return new CarDeviceCommandResult(
            ok: false,
            transport: 'gprs',
            error: 'HTTP '.$response->status(),
            failureCode: $response->status() >= 500 ? 'gateway_unreachable' : 'command_failed',
        );
    }

    public function isDeviceOnline(string $imei): bool
    {
        $base = rtrim((string) config('car_control.gprs.internal_base_url', ''), '/');
        if ($base === '' || $imei === '') {
            return false;
        }

        $token = config('car_control.gprs.internal_token');
        $path = str_replace('{imei}', rawurlencode($imei), (string) config('car_control.gprs.device_status_path', 'devices/{imei}/status'));
        $url = $base.'/'.ltrim($path, '/');
        $shortTimeout = max(1, (int) config('car_control.gprs.device_status_timeout_seconds', 5));

        try {
            $pending = Http::timeout($shortTimeout)->acceptJson();
            if (filled($token)) {
                $pending = $pending->withToken((string) $token);
            }
            $response = $pending->get($url);
        } catch (\Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        if ($response->json('online') === true) {
            return true;
        }

        $status = $response->json('status');
        if (is_string($status) && strtolower($status) === 'online') {
            return true;
        }

        return false;
    }
}
