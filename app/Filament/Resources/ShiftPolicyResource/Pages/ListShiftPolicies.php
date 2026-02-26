<?php

namespace App\Filament\Resources\ShiftPolicyResource\Pages;

use App\Filament\Resources\ShiftPolicyResource;
use App\Models\ShiftPolicy;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListShiftPolicies extends ListRecords
{
    protected static string $resource = ShiftPolicyResource::class;

    public function mount(): void
    {
        $policy = ShiftPolicy::first();
        if (! $policy) {
            $policy = DB::transaction(function () {
                return ShiftPolicy::create([
                    'name' => 'Default',
                    'min_duration_hours' => 4,
                    'allowed_durations_json' => [4, 6, 8, 10, 12],
                    'vehicle_downtime_hours' => 0,
                    'planning_window_days' => 14,
                    'time_slot_minutes' => 15,
                    'timezone' => 'Europe/Riga',
                ]);
            });
        }
        $this->redirect(ShiftPolicyResource::getUrl('edit', ['record' => $policy]));
    }
}
