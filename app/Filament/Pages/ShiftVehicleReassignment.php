<?php

namespace App\Filament\Pages;

use App\Enums\VehicleStatus;
use App\Exceptions\ShiftVehicleReassignmentException;
use App\Models\FleetVehicle;
use App\Models\ShiftPolicy;
use App\Services\ShiftVehicleReassignmentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ShiftVehicleReassignment extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.pages.shift-vehicle-reassignment';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?string $navigationLabel = 'Shift vehicle reassignment';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Shift vehicle reassignment';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    protected function getHeaderActions(): array
    {
        $tz = ShiftPolicy::active()?->timezone ?? 'Europe/Riga';

        return [
            Action::make('reassign')
                ->label('Reassign future booked shifts')
                ->icon('heroicon-o-arrows-right-left')
                ->modalHeading('Move future booked shifts to another vehicle')
                ->modalDescription('Only shifts with status Booked and start time on or after “Effective from” are updated. Completed and past booked shifts are never changed. Same home station is required; target vehicle must be available for each slot.')
                ->modalSubmitActionLabel('Apply')
                ->form([
                    Select::make('from_vehicle_id')
                        ->label('From vehicle')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return FleetVehicle::query()
                                ->where('status', VehicleStatus::Active)
                                ->where(function ($q) use ($search) {
                                    $q->where('registration_number', 'like', '%'.$search.'%')
                                        ->orWhere('brand', 'like', '%'.$search.'%')
                                        ->orWhere('model', 'like', '%'.$search.'%');
                                })
                                ->orderBy('registration_number')
                                ->limit(40)
                                ->get()
                                ->mapWithKeys(fn (FleetVehicle $v) => [
                                    $v->id => $v->registration_number.' — '.trim(($v->brand ?? '').' '.($v->model ?? '')).' (station #'.$v->home_station_id.')',
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }
                            $v = FleetVehicle::find($value);

                            return $v ? $v->registration_number.' — '.trim(($v->brand ?? '').' '.($v->model ?? '')) : null;
                        }),
                    Select::make('to_vehicle_id')
                        ->label('To vehicle (replacement)')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            return FleetVehicle::query()
                                ->where('status', VehicleStatus::Active)
                                ->where(function ($q) use ($search) {
                                    $q->where('registration_number', 'like', '%'.$search.'%')
                                        ->orWhere('brand', 'like', '%'.$search.'%')
                                        ->orWhere('model', 'like', '%'.$search.'%');
                                })
                                ->orderBy('registration_number')
                                ->limit(40)
                                ->get()
                                ->mapWithKeys(fn (FleetVehicle $v) => [
                                    $v->id => $v->registration_number.' — '.trim(($v->brand ?? '').' '.($v->model ?? '')).' (station #'.$v->home_station_id.')',
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (! $value) {
                                return null;
                            }
                            $v = FleetVehicle::find($value);

                            return $v ? $v->registration_number.' — '.trim(($v->brand ?? '').' '.($v->model ?? '')) : null;
                        }),
                    DateTimePicker::make('effective_from')
                        ->label('Effective from')
                        ->helperText('Policy timezone: '.$tz.'. Only shifts starting at or after this moment are moved.')
                        ->timezone($tz)
                        ->default(now()->timezone($tz))
                        ->required()
                        ->native(false)
                        ->seconds(false),
                    Textarea::make('note')
                        ->label('Audit note (optional)')
                        ->rows(2)
                        ->maxLength(2000),
                ])
                ->action(function (array $data) use ($tz): void {
                    try {
                        $service = app(ShiftVehicleReassignmentService::class);
                        $effective = Carbon::parse($data['effective_from'], $tz);
                        $result = $service->reassignFutureBookedShifts(
                            (int) $data['from_vehicle_id'],
                            (int) $data['to_vehicle_id'],
                            $effective,
                            auth()->id(),
                            $data['note'] ?? null
                        );
                        Notification::make()
                            ->title('Reassignment complete')
                            ->body('Updated '.$result['updated'].' shift(s). Batch ID: '.$result['batch_id'])
                            ->success()
                            ->send();
                    } catch (ShiftVehicleReassignmentException $e) {
                        Notification::make()
                            ->title('Reassignment failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
