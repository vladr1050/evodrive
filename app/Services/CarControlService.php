<?php

namespace App\Services;

use App\Enums\ShiftStatus;
use App\Models\CarCommand;
use App\Models\FleetVehicle;
use App\Models\Shift;
use App\Services\CarControl\CarActionCommandResolver;
use App\Services\CarControl\CarControlTransportRouter;
use App\Services\CarControl\CarDeviceCommandResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Car control: access window, rate limit, idempotency, command execution.
 * Delivery uses {@see CarControlTransportRouter} (SMS / GPRS / AUTO) without changing action → payload mapping.
 */
class CarControlService
{
    public function __construct(
        protected CarActionCommandResolver $commandResolver,
        protected CarControlTransportRouter $transportRouter,
    ) {}

    /**
     * Get current car control context for driver (shift + vehicle) or reason why access denied.
     *
     * @return array{allowed: true, shift: Shift, vehicle: FleetVehicle}|array{allowed: false, reason: string}
     */
    public function getDriverCarControlContext(int $driverId, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $windowMinutes = config('car_control.window_minutes', 45);

        $shift = Shift::query()
            ->where('driver_id', $driverId)
            ->where('status', '!=', ShiftStatus::Cancelled)
            ->where('starts_at', '<=', $now->copy()->addMinutes($windowMinutes))
            ->where('ends_at', '>=', $now->copy()->subMinutes($windowMinutes))
            ->with('vehicle')
            ->orderByRaw('(starts_at <= ? AND ends_at >= ?) DESC', [$now, $now])
            ->orderBy('starts_at')
            ->first();

        if (! $shift) {
            $next = Shift::query()
                ->where('driver_id', $driverId)
                ->where('status', ShiftStatus::Booked)
                ->where('starts_at', '>', $now)
                ->orderBy('starts_at')
                ->first();
            if ($next) {
                $diff = $now->diffInMinutes($next->starts_at, false);
                if ($diff > $windowMinutes) {
                    return ['allowed' => false, 'reason' => 'too_early'];
                }
            }
            $ended = Shift::query()
                ->where('driver_id', $driverId)
                ->where('status', '!=', ShiftStatus::Cancelled)
                ->where('ends_at', '<', $now)
                ->orderByDesc('ends_at')
                ->first();
            if ($ended && $now->diffInMinutes($ended->ends_at, false) < -$windowMinutes) {
                return ['allowed' => false, 'reason' => 'too_late'];
            }

            return ['allowed' => false, 'reason' => 'no_shift'];
        }

        $vehicle = $shift->vehicle;
        if (! $vehicle) {
            return ['allowed' => false, 'reason' => 'no_shift'];
        }

        $transportMode = $this->transportMode();
        $hasSim = filled(trim((string) ($vehicle->sim ?? '')));
        $hasImei = filled(trim((string) ($vehicle->imei ?? '')));

        if ($transportMode === 'sms' && ! $hasSim) {
            return ['allowed' => false, 'reason' => 'car_not_configured'];
        }
        if ($transportMode === 'gprs' && ! $hasImei) {
            return ['allowed' => false, 'reason' => 'car_not_configured'];
        }
        if ($transportMode === 'auto' && ! $hasSim && ! $hasImei) {
            return ['allowed' => false, 'reason' => 'car_not_configured'];
        }

        return ['allowed' => true, 'shift' => $shift, 'vehicle' => $vehicle];
    }

    /**
     * Check rate limit: not more than 1 command per driver per X sec, 1 per vehicle per Y sec.
     */
    public function canExecuteCommand(int $driverId, int $vehicleId): bool
    {
        $driverSec = config('car_control.rate_limit_driver_seconds', 15);
        $vehicleSec = config('car_control.rate_limit_vehicle_seconds', 10);

        $lastByDriver = CarCommand::where('driver_id', $driverId)->orderByDesc('created_at')->first();
        if ($lastByDriver && $lastByDriver->created_at->addSeconds($driverSec)->isFuture()) {
            return false;
        }
        $lastByVehicle = CarCommand::where('vehicle_id', $vehicleId)->orderByDesc('created_at')->first();
        if ($lastByVehicle && $lastByVehicle->created_at->addSeconds($vehicleSec)->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Get last in-progress (queued) command for this driver to enforce idempotency.
     */
    public function getLastInProgressCommand(int $driverId): ?CarCommand
    {
        return CarCommand::where('driver_id', $driverId)
            ->where('status', CarCommand::STATUS_QUEUED)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Execute car control action. Validates context, rate limit, idempotency; creates CarCommand and delivers payloads.
     *
     * @return array{ok: bool, message: string, command?: CarCommand}
     */
    public function executeAction(int $driverId, string $action, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $context = $this->getDriverCarControlContext($driverId, $now);
        if (! ($context['allowed'] ?? false)) {
            return ['ok' => false, 'message' => $this->reasonToMessage($context['reason'] ?? 'no_shift')];
        }

        $shift = $context['shift'];
        $vehicle = $context['vehicle'];
        $transportMode = $this->transportMode();
        $phone = preg_replace('/\D/', '', (string) $vehicle->sim);
        $imei = trim((string) ($vehicle->imei ?? ''));

        if ($transportMode === 'sms' && $phone === '') {
            return ['ok' => false, 'message' => 'Car SIM (phone) not configured.'];
        }
        if ($transportMode === 'gprs') {
            if ($imei === '') {
                return ['ok' => false, 'message' => 'Vehicle IMEI not configured for GPRS control.'];
            }
            if (! filled(config('car_control.gprs.internal_base_url'))) {
                return ['ok' => false, 'message' => 'GPRS control is not configured on the server.'];
            }
        }
        if ($transportMode === 'auto' && ! $this->transportRouter->canAutoAttempt($vehicle)) {
            return ['ok' => false, 'message' => 'Vehicle cannot be reached: add SIM for SMS fallback, or IMEI with online GPRS.'];
        }

        if (! $this->canExecuteCommand($driverId, $vehicle->id)) {
            return ['ok' => false, 'message' => 'Command is in progress… Please wait.'];
        }

        $inProgress = $this->getLastInProgressCommand($driverId);
        if ($inProgress) {
            return ['ok' => false, 'message' => 'Command is in progress…', 'command' => $inProgress];
        }

        $payloads = $this->commandResolver->resolve($action);
        if ($payloads === []) {
            return ['ok' => false, 'message' => 'Unknown action.'];
        }

        $smsTo = $vehicle->sim ?: ($imei !== '' ? 'imei:'.$imei : '-');

        $command = CarCommand::create([
            'driver_id' => $driverId,
            'shift_id' => $shift->id,
            'vehicle_id' => $vehicle->id,
            'action' => $action,
            'sms_to' => $smsTo,
            'sms_payloads' => $payloads,
            'status' => CarCommand::STATUS_QUEUED,
        ]);

        $delivery = $this->transportRouter->deliverSequential($transportMode, $vehicle, $payloads);

        if (! $delivery['ok']) {
            /** @var CarDeviceCommandResult $last */
            $last = $delivery['last_result'] ?? null;
            $errorText = $last instanceof CarDeviceCommandResult
                ? ($last->error ?? 'Command failed')
                : 'Command failed';

            $command->update([
                'status' => CarCommand::STATUS_FAILED,
                'error_message' => $errorText,
                'provider_message_ids' => $delivery['meta']['provider_refs'] ?? [],
                'transport_meta' => $delivery['meta'],
            ]);

            Log::channel('stack')->warning('CarControlService: command delivery failed', [
                'command_id' => $command->id,
                'transport_mode' => $transportMode,
                'failure_code' => $last instanceof CarDeviceCommandResult ? $last->failureCode : null,
            ]);

            return [
                'ok' => false,
                'message' => $this->deliveryFailureUserMessage($last, $transportMode),
                'command' => $command,
            ];
        }

        $command->update([
            'status' => CarCommand::STATUS_SENT,
            'provider_message_ids' => $delivery['meta']['provider_refs'] ?? [],
            'transport_meta' => $delivery['meta'],
        ]);

        return ['ok' => true, 'message' => $this->actionSuccessMessage($action), 'command' => $command];
    }

    private function transportMode(): string
    {
        $mode = strtolower((string) config('car_control.default_transport', 'sms'));

        return in_array($mode, ['sms', 'gprs', 'auto'], true) ? $mode : 'sms';
    }

    private function deliveryFailureUserMessage(?CarDeviceCommandResult $last, string $transportMode): string
    {
        if (! $last instanceof CarDeviceCommandResult) {
            return 'Command failed.';
        }

        if ($last->transport === 'sms') {
            $err = (string) ($last->error ?? '');
            if ($err === 'SMS provider not configured') {
                return 'Car control is temporarily unavailable. Please contact support.';
            }
            if ($err === 'InvalidSender') {
                return 'SMS sender ID is not set up for this account. Please contact support.';
            }
            if ($last->failureCode === 'sms_number_missing') {
                return 'Car SIM (phone) not configured for SMS.';
            }

            return $err !== '' ? 'SMS failed: '.$err : 'SMS failed.';
        }

        if ($last->transport === 'gprs') {
            return match ($last->failureCode) {
                'timeout', 'device_offline' => 'Vehicle did not respond in time. Try again or use SMS if available.',
                'gateway_not_configured', 'gateway_unreachable' => 'Car control service is temporarily unavailable.',
                default => 'Remote command failed: '.($last->error ?: $last->failureCode ?: 'unknown'),
            };
        }

        return $last->error ?: 'Command failed.';
    }

    private function reasonToMessage(string $reason): string
    {
        return match ($reason) {
            'too_early' => 'More than 45 minutes until shift start.',
            'too_late' => 'Shift ended, control window closed.',
            'no_shift' => 'No shift found.',
            'car_not_configured' => 'Vehicle not configured for remote control (SIM and/or IMEI).',
            default => 'No active shift.',
        };
    }

    private function actionSuccessMessage(string $action): string
    {
        return match ($action) {
            CarCommand::ACTION_START_SHIFT => 'Shift started. Car opened, engine unlocked.',
            CarCommand::ACTION_OPEN_CAR => 'Car opened.',
            CarCommand::ACTION_CLOSE_CAR => 'Car closed.',
            CarCommand::ACTION_END_SHIFT => 'Shift ended. Engine locked, car closed.',
            default => 'Done.',
        };
    }
}
