<?php

namespace App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;

use App\Exceptions\VehicleServiceBlockException;
use App\Filament\Resources\FleetVehicleServiceBlockResource;
use App\Models\FleetVehicleServiceBlock;
use App\Services\VehicleServiceBlockService;
use Carbon\Carbon;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditFleetVehicleServiceBlock extends EditRecord
{
    protected static string $resource = FleetVehicleServiceBlockResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var FleetVehicleServiceBlock $record */
        if ($record->isCancelled()) {
            return $record;
        }

        $tz = VehicleServiceBlockService::policyTimezone();
        $starts = $this->toUtc($data['starts_at'], $tz);
        $ends = $this->toUtc($data['ends_at'], $tz);

        try {
            app(VehicleServiceBlockService::class)->updateWindow($record, $starts, $ends);
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

        $record->update(['note' => $data['note'] ?? null]);

        return $record->refresh();
    }

    private function toUtc(mixed $value, string $tz): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse($value, $tz)->utc();
    }
}
