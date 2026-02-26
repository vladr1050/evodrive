<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftPolicyResource\Pages;
use App\Models\ShiftPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class ShiftPolicyResource extends Resource
{
    protected static ?string $model = ShiftPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?string $navigationLabel = 'Shift Policy';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    public static function form(Form $form): Form
    {
        $weekdays = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
        return $form
            ->schema([
                Forms\Components\Section::make('Durations & timing')
                    ->description('Allowed shift lengths and minimum duration.')
                    ->schema([
                        Forms\Components\Select::make('allowed_durations_json')
                            ->label('Allowed durations (hours)')
                            ->options(array_combine($a = [4, 6, 8, 10, 12], array_map(fn ($h) => "{$h}h", $a)))
                            ->multiple()
                            ->required()
                            ->helperText('Shift lengths drivers can choose.'),
                        Forms\Components\TextInput::make('min_duration_hours')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(4)
                            ->required()
                            ->helperText('Minimum shift length in hours.'),
                        Forms\Components\TextInput::make('vehicle_downtime_hours')
                            ->label('Vehicle downtime between shifts (hours)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->required()
                            ->helperText('Gap required between shifts on the same vehicle.'),
                        Forms\Components\TextInput::make('time_slot_minutes')
                            ->label('Time slot (minutes)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(60)
                            ->default(15)
                            ->required()
                            ->helperText('Start times must align to this (e.g. 15 = 08:00, 08:15, 08:30).'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Planning & limits')
                    ->schema([
                        Forms\Components\Toggle::make('require_return_to_home_station')
                            ->label('Require return to home station')
                            ->helperText('If enabled, shifts must end at vehicle home station.')
                            ->default(false),
                        Forms\Components\Select::make('planning_opens_weekday')
                            ->label('Planning opens weekday')
                            ->options($weekdays)
                            ->helperText('Day of week when planning window opens (1=Mon, 7=Sun).'),
                        Forms\Components\TextInput::make('planning_window_days')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(90)
                            ->default(14)
                            ->required()
                            ->helperText('How many days ahead drivers can book.'),
                        Forms\Components\TextInput::make('max_shifts_per_driver_per_day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->nullable()
                            ->helperText('Leave empty for no limit.'),
                        Forms\Components\TextInput::make('timezone')
                            ->required()
                            ->maxLength(50)
                            ->default('Europe/Riga')
                            ->helperText('IANA timezone (e.g. Europe/Riga).')
                            ->rule('timezone'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftPolicies::route('/'),
            'edit' => Pages\EditShiftPolicy::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getModelLabel(): string
    {
        return 'Shift Policy';
    }
}
