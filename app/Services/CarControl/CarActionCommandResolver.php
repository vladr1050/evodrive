<?php

namespace App\Services\CarControl;

use App\Models\CarCommand;

/**
 * Maps high-level actions to ordered device command strings (unchanged business rules).
 */
final class CarActionCommandResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $action): array
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
}
