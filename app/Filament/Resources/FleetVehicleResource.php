<?php

namespace App\Filament\Resources;

use App\Enums\VehicleCommandTransport;
use App\Enums\VehicleStatus;
use App\Filament\Resources\FleetVehicleResource\Pages;
use App\Filament\Resources\FleetVehicleResource\RelationManagers;
use App\Models\FleetVehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FleetVehicleResource extends Resource
{
    protected static ?string $model = FleetVehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?string $navigationLabel = 'Vehicles';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    public static function form(Form $form): Form
    {
        $currentYear = (int) date('Y');

        return $form
            ->schema([
                Forms\Components\Section::make('Vehicle')
                    ->schema([
                        Forms\Components\TextInput::make('brand')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('model')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('year')
                            ->numeric()
                            ->minValue(1990)
                            ->maxValue($currentYear + 1),
                        Forms\Components\TextInput::make('color')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('atd_license_number')
                            ->label('ATD license number')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('registration_number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        Forms\Components\Select::make('home_station_id')
                            ->label('Home station')
                            ->relationship(
                                'homeStation',
                                'name',
                                fn ($query) => $query->where('is_active', true)->orderBy('name')
                            )
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->options(collect(VehicleStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                            ->default(VehicleStatus::Active)
                            ->required(),
                        Forms\Components\TextInput::make('imei')
                            ->label('IMEI')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('sim')
                            ->label('SIM (phone for SMS car control)')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('37120000000'),
                        Forms\Components\Select::make('command_transport')
                            ->label('Command delivery channel')
                            ->native(false)
                            ->placeholder('Fleet default (CAR_CONTROL_TRANSPORT in .env)')
                            ->options(collect(VehicleCommandTransport::cases())->mapWithKeys(
                                fn (VehicleCommandTransport $c) => [$c->value => $c->label()]
                            ))
                            ->nullable()
                            ->helperText('Per vehicle: SMS, GPRS (Teltonika gateway), or Auto (GPRS when device online, else SMS). Empty = fleet default.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Brand / Model')
                    ->formatStateUsing(fn (FleetVehicle $r) => trim(($r->brand ?? '').' '.($r->model ?? '')))
                    ->searchable(query: function (Builder $q, string $search) {
                        if ($q->getModel() === null) {
                            return;
                        }
                        $search = trim($search);
                        if ($search === '') {
                            return;
                        }
                        $q->where(function (Builder $sub) use ($search) {
                            if ($sub->getModel() === null) {
                                return;
                            }
                            $sub->where('brand', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('homeStation.name')
                    ->label('Home station')
                    ->sortable(),
                Tables\Columns\TextColumn::make('imei')
                    ->label('IMEI')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sim')
                    ->label('SIM')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('command_transport')
                    ->label('Command channel')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return 'Fleet default';
                        }
                        if ($state instanceof VehicleCommandTransport) {
                            return $state->label();
                        }

                        return (string) $state;
                    })
                    ->color(fn ($state) => $state === null ? 'gray' : 'info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof VehicleStatus ? ucfirst($state->value) : (string) $state)
                    ->color(fn ($state) => match (true) {
                        $state === VehicleStatus::Active => 'success',
                        $state === VehicleStatus::Maintenance => 'warning',
                        $state === VehicleStatus::Blocked => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('registration_number')
            ->filters([
                Tables\Filters\SelectFilter::make('home_station_id')
                    ->label('Station')
                    ->relationship('homeStation', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(VehicleStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVehicles::route('/'),
            'create' => Pages\CreateFleetVehicle::route('/create'),
            'edit' => Pages\EditFleetVehicle::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VehicleCommandDeliveriesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
