<?php

namespace App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;

use App\Exceptions\VehicleServiceBlockException;
use App\Filament\Resources\FleetVehicleServiceBlockResource;
use App\Models\FleetVehicleServiceBlock;
use App\Services\VehicleServiceBlockService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateFleetVehicleServiceBlock extends CreateRecord
{
    protected static string $resource = FleetVehicleServiceBlockResource::class;

    public function mount(): void
    {
        parent::mount();
        $vid = request()->query('fleet_vehicle_id');
        if ($vid !== null && $vid !== '') {
            $this->form->fill([
                'fleet_vehicle_id' => (int) $vid,
            ]);
        }
    }

    protected function handleRecordCreation(array $data): FleetVehicleServiceBlock
    {
        $tz = VehicleServiceBlockService::policyTimezone();
        $starts = $this->toUtc($data['starts_at'], $tz);
        $ends = $this->toUtc($data['ends_at'], $tz);

        try {
            return app(VehicleServiceBlockService::class)->create(
                (int) $data['fleet_vehicle_id'],
                $starts,
                $ends,
                $data['note'] ?? null,
                auth()->id(),
            );
        } catch (VehicleServiceBlockException $e) {
            $body = $e->getMessage();
            if ($e->suggestions !== []) {
                $lines = collect($e->suggestions)->map(function (array $s) {
                    return Carbon::parse($s['starts_at'])->toIso8601String().' → '.Carbon::parse($s['ends_at'])->toIso8601String();
                })->implode("\n");
                $body .= "\n\nSuggested windows (ISO UTC):\n".$lines;
            }
            throw ValidationException::withMessages(['starts_at' => $body]);
        }
    }

    private function toUtc(mixed $value, string $tz): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse($value, $tz)->utc();
    }
}
