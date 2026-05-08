<?php

namespace App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;

use App\Exceptions\VehicleServiceBlockException;
use App\Filament\Resources\FleetVehicleServiceBlockResource;
use App\Models\FleetVehicleServiceBlock;
use App\Services\VehicleServiceBlockService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
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

        $starts = $this->toUtc($data['starts_at']);
        $ends = $this->toUtc($data['ends_at']);

        try {
            app(VehicleServiceBlockService::class)->updateWindow($record, $starts, $ends);
        } catch (VehicleServiceBlockException $e) {
            $tz = VehicleServiceBlockService::policyTimezone();
            $title = match ($e->reasonCode) {
                'OVERLAPS_SHIFTS' => 'Range overlaps booked shifts',
                'OVERLAPS_SERVICE' => 'Range overlaps another service block',
                default => 'Cannot update service block',
            };
            $body = $e->getMessage();
            if ($e->suggestions !== []) {
                $lines = collect($e->suggestions)->map(function (array $s) use ($tz) {
                    $a = Carbon::parse($s['starts_at'])->setTimezone($tz)->format('Y-m-d H:i');
                    $b = Carbon::parse($s['ends_at'])->setTimezone($tz)->format('Y-m-d H:i');

                    return $a.' — '.$b;
                })->implode("\n");
                $body .= "\n\nSuggested free windows (".$tz."):\n".$lines;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'data.starts_at' => $title,
                'data.ends_at' => $title,
            ]);
        }

        $record->update(['note' => $data['note'] ?? null]);

        return $record->refresh();
    }

    private function toUtc(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse($value, config('app.timezone'))->utc();
    }
}
