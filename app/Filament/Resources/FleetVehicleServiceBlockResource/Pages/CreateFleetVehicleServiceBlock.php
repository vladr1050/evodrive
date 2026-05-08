<?php

namespace App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;

use App\Exceptions\VehicleServiceBlockException;
use App\Filament\Resources\FleetVehicleServiceBlockResource;
use App\Models\FleetVehicleServiceBlock;
use App\Services\VehicleServiceBlockService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
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
        Log::channel('stack')->warning('CreateFleetVehicleServiceBlock: handleRecordCreation called', [
            'auth_id' => auth()->id(),
            'data' => $data,
        ]);

        $starts = $this->toUtc($data['starts_at']);
        $ends = $this->toUtc($data['ends_at']);

        try {
            $block = app(VehicleServiceBlockService::class)->create(
                (int) $data['fleet_vehicle_id'],
                $starts,
                $ends,
                $data['note'] ?? null,
                auth()->id(),
            );

            Log::channel('stack')->warning('CreateFleetVehicleServiceBlock: created', ['id' => $block->id]);

            return $block;
        } catch (VehicleServiceBlockException $e) {
            Log::channel('stack')->warning('CreateFleetVehicleServiceBlock: VehicleServiceBlockException', [
                'reason' => $e->reasonCode,
                'message' => $e->getMessage(),
                'suggestions' => $e->suggestions,
            ]);

            $tz = VehicleServiceBlockService::policyTimezone();
            $title = match ($e->reasonCode) {
                'OVERLAPS_SHIFTS' => 'Range overlaps booked shifts',
                'OVERLAPS_SERVICE' => 'Range overlaps another service block',
                default => 'Cannot create service block',
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
    }

    private function toUtc(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse($value, config('app.timezone'))->utc();
    }
}
