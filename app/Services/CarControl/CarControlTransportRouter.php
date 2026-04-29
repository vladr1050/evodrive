<?php

namespace App\Services\CarControl;

use App\Models\FleetVehicle;
use App\Models\VehicleCommandDelivery;
use Illuminate\Support\Facades\Log;

/**
 * Runs sequential device commands using per-vehicle transport (sms | gprs | auto), with optional delivery logging.
 */
final class CarControlTransportRouter
{
    public function __construct(
        protected SmsCarDeviceTransport $smsTransport,
        protected GprsCarDeviceTransport $gprsTransport,
    ) {}

    /**
     * @param  list<string>  $payloads
     * @param  array{car_command_id: int, driver_id: int, shift_id: int}|null  $deliveryLogContext
     * @return array{ok: bool, meta: array<string, mixed>, last_result?: CarDeviceCommandResult}
     */
    public function deliverSequential(string $mode, FleetVehicle $vehicle, array $payloads, ?array $deliveryLogContext = null): array
    {
        $mode = strtolower($mode);
        if (! in_array($mode, ['sms', 'gprs', 'auto'], true)) {
            $mode = 'sms';
        }

        $phone = preg_replace('/\D/', '', (string) $vehicle->sim);
        $imei = trim((string) ($vehicle->imei ?? ''));
        $commandTimeout = max(1, (int) config('car_control.gprs.command_timeout_seconds', 30));

        $meta = [
            'default_transport' => $mode,
            'fallback_used' => false,
            'steps' => [],
            'provider_refs' => [],
        ];

        $gprsBaseConfigured = filled(config('car_control.gprs.internal_base_url'));
        $gprsReady = $gprsBaseConfigured && $imei !== '';

        foreach ($payloads as $index => $text) {
            if ($index > 0) {
                $delay = $this->pairDelaySeconds($mode);
                if ($delay > 0) {
                    sleep($delay);
                }
            }

            $stepContext = ['fallback_reason' => null];
            $result = $this->deliverOneStep(
                $mode,
                $vehicle,
                $text,
                $phone,
                $imei,
                $commandTimeout,
                $gprsReady,
                $meta,
                $stepContext,
            );

            if ($deliveryLogContext !== null) {
                $this->persistDeliveryLog($deliveryLogContext, $vehicle, $index + 1, $mode, $text, $result);
            }

            $meta['steps'][] = [
                'sequence' => $index + 1,
                'transport' => $result->transport,
                'ok' => $result->ok,
                'failure_code' => $result->failureCode,
                'fallback_reason' => $stepContext['fallback_reason'],
            ];
            if ($result->ok) {
                foreach ($result->providerRefs as $ref) {
                    $meta['provider_refs'][] = $ref;
                }
            }

            if (! $result->ok) {
                return ['ok' => false, 'meta' => $meta, 'last_result' => $result];
            }
        }

        return ['ok' => true, 'meta' => $meta];
    }

    /**
     * @param  array<string, mixed>  $meta  by reference for fallback_used
     * @param  array{fallback_reason: ?string}  $stepContext
     */
    private function deliverOneStep(
        string $mode,
        FleetVehicle $vehicle,
        string $commandText,
        string $phone,
        string $imei,
        int $commandTimeout,
        bool $gprsReady,
        array &$meta,
        array &$stepContext,
    ): CarDeviceCommandResult {
        if ($mode === 'sms') {
            return $this->smsTransport->send(new CarDeviceTransportSendRequest(
                commandText: $commandText,
                smsPhoneDigits: $phone !== '' ? $phone : null,
            ));
        }

        if ($mode === 'gprs') {
            return $this->gprsTransport->send(new CarDeviceTransportSendRequest(
                commandText: $commandText,
                imei: $imei !== '' ? $imei : null,
                timeoutSeconds: $commandTimeout,
            ));
        }

        // AUTO
        $tryGprsFirst = $gprsReady && $this->gprsTransport->isDeviceOnline($imei);
        if ($tryGprsFirst) {
            $gprsResult = $this->gprsTransport->send(new CarDeviceTransportSendRequest(
                commandText: $commandText,
                imei: $imei,
                timeoutSeconds: $commandTimeout,
            ));
            if ($gprsResult->ok) {
                return $gprsResult;
            }
            if ($gprsResult->allowsSmsFallback() && $phone !== '') {
                $meta['fallback_used'] = true;
                $stepContext['fallback_reason'] = $gprsResult->failureCode;
                Log::channel('stack')->info('CarControlTransportRouter: AUTO GPRS failed, SMS fallback', [
                    'vehicle_id' => $vehicle->id,
                    'imei' => $imei,
                    'failure_code' => $gprsResult->failureCode,
                ]);

                return $this->smsTransport->send(new CarDeviceTransportSendRequest(
                    commandText: $commandText,
                    smsPhoneDigits: $phone,
                ));
            }

            return $gprsResult;
        }

        // Device offline, gateway missing, or GPRS not preferred → SMS path
        if ($phone === '') {
            return new CarDeviceCommandResult(
                ok: false,
                transport: 'sms',
                error: 'Car SIM (phone) not configured for SMS fallback.',
                failureCode: 'sms_number_missing',
                responseDetail: null,
            );
        }

        $stepContext['fallback_reason'] = ! $gprsReady ? 'gprs_unavailable' : 'device_offline';

        return $this->smsTransport->send(new CarDeviceTransportSendRequest(
            commandText: $commandText,
            smsPhoneDigits: $phone,
        ));
    }

    private function pairDelaySeconds(string $mode): int
    {
        if ($mode === 'sms' || $mode === 'auto') {
            return (int) config('car_control.pair_sms_delay_seconds', 3);
        }

        return (int) config('car_control.gprs.pair_command_delay_seconds', 0);
    }

    /**
     * AUTO mode needs either SMS number or an online GPRS device to make progress.
     */
    public function canAutoAttempt(FleetVehicle $vehicle): bool
    {
        $phone = preg_replace('/\D/', '', (string) $vehicle->sim);
        if ($phone !== '') {
            return true;
        }

        $imei = trim((string) ($vehicle->imei ?? ''));
        $gprsReady = filled(config('car_control.gprs.internal_base_url')) && $imei !== '';

        return $gprsReady && $this->gprsTransport->isDeviceOnline($imei);
    }

    /**
     * @param  array{car_command_id: int, driver_id: int, shift_id: int}  $ctx
     */
    private function persistDeliveryLog(array $ctx, FleetVehicle $vehicle, int $sequence, string $requestedMode, string $commandText, CarDeviceCommandResult $result): void
    {
        VehicleCommandDelivery::query()->create([
            'car_command_id' => $ctx['car_command_id'],
            'vehicle_id' => $vehicle->id,
            'driver_id' => $ctx['driver_id'],
            'shift_id' => $ctx['shift_id'],
            'sequence' => $sequence,
            'requested_mode' => strtolower($requestedMode),
            'effective_transport' => $result->transport,
            'sim_number' => (string) ($vehicle->sim ?? ''),
            'command_text' => $commandText,
            'ok' => $result->ok,
            'failure_code' => $result->failureCode,
            'error_message' => $result->error,
            'provider_refs' => $result->providerRefs !== [] ? $result->providerRefs : null,
            'response_detail' => $result->responseDetail,
        ]);
    }
}
