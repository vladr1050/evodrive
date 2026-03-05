<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Enums\ShiftStatus;
use App\Models\CarCommand;
use App\Models\Driver;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Car control via SMS: access window, rate limit, idempotency, command execution.
 */
class CarControlService
{
    public function __construct(
        protected SmsProviderInterface $smsProvider
    ) {
    }

    /**
     * Get current car control context for driver (shift + vehicle) or reason why access denied.
     *
     * @return array{allowed: true, shift: Shift, vehicle: \App\Models\FleetVehicle}|array{allowed: false, reason: string}
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
        if (empty(trim((string) $vehicle->sim))) {
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
     * Execute car control action. Validates context, rate limit, idempotency; creates CarCommand and sends SMS.
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
        $phone = preg_replace('/\D/', '', $vehicle->sim);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Car SIM (phone) not configured.'];
        }

        if (! $this->canExecuteCommand($driverId, $vehicle->id)) {
            return ['ok' => false, 'message' => 'Command is in progress… Please wait.'];
        }

        $inProgress = $this->getLastInProgressCommand($driverId);
        if ($inProgress) {
            return ['ok' => false, 'message' => 'Command is in progress…', 'command' => $inProgress];
        }

        $payloads = $this->getPayloadsForAction($action);
        if ($payloads === []) {
            return ['ok' => false, 'message' => 'Unknown action.'];
        }

        $command = CarCommand::create([
            'driver_id' => $driverId,
            'shift_id' => $shift->id,
            'vehicle_id' => $vehicle->id,
            'action' => $action,
            'sms_to' => $vehicle->sim,
            'sms_payloads' => $payloads,
            'status' => CarCommand::STATUS_QUEUED,
        ]);

        $delaySeconds = config('car_control.pair_sms_delay_seconds', 3);
        $messageIds = [];
        foreach ($payloads as $i => $text) {
            if ($i > 0 && $delaySeconds > 0) {
                sleep($delaySeconds);
            }
            $result = $this->smsProvider->send($phone, $text);
            if (isset($result['message_id'])) {
                $messageIds[] = $result['message_id'];
            }
            if (($result['status'] ?? '') === 'failed') {
                $command->update([
                    'status' => CarCommand::STATUS_FAILED,
                    'error_message' => $result['error'] ?? 'Send failed',
                    'provider_message_ids' => $messageIds,
                ]);
                Log::channel('stack')->warning('CarControlService: SMS send failed', [
                    'command_id' => $command->id,
                    'error' => $result['error'] ?? null,
                ]);

                $err = $result['error'] ?? '';
                $userMessage = match (true) {
                    $err === 'SMS provider not configured' => 'Car control is temporarily unavailable. Please contact support.',
                    $err === 'InvalidSender' => 'SMS sender ID is not set up for this account. Please contact support.',
                    default => 'SMS failed: ' . ($err ?: 'Unknown error'),
                };
                return ['ok' => false, 'message' => $userMessage, 'command' => $command];
            }
        }

        $command->update([
            'status' => CarCommand::STATUS_SENT,
            'provider_message_ids' => $messageIds,
        ]);

        return ['ok' => true, 'message' => $this->actionSuccessMessage($action), 'command' => $command];
    }

    private function getPayloadsForAction(string $action): array
    {
        $open = config('car_control.commands.open_car', 'youto youto lvcanopenalldoors');
        $close = config('car_control.commands.close_car', 'youto youto lvcanclosealldoors');
        $unlock = config('car_control.commands.unlock_engine', 'youto youto setdigout 00 0 0');
        $lock = config('car_control.commands.lock_engine', 'youto youto setdigout 10 0 0');

        return match ($action) {
            CarCommand::ACTION_START_SHIFT => [$unlock, $open],
            CarCommand::ACTION_OPEN_CAR => [$open],
            CarCommand::ACTION_CLOSE_CAR => [$close],
            CarCommand::ACTION_END_SHIFT => [$lock, $close],
            default => [],
        };
    }

    private function reasonToMessage(string $reason): string
    {
        return match ($reason) {
            'too_early' => 'More than 45 minutes until shift start.',
            'too_late' => 'Shift ended, control window closed.',
            'no_shift' => 'No shift found.',
            'car_not_configured' => 'Vehicle not configured (no SIM / number).',
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
