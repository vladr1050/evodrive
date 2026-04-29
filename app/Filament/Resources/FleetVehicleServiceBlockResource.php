<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetVehicleServiceBlockResource\Pages;
use App\Models\FleetVehicleServiceBlock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FleetVehicleServiceBlockResource extends Resource
{
    protected static ?string $model = FleetVehicleServiceBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?string $navigationLabel = 'Vehicle service';

    protected static ?string $modelLabel = 'Service block';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    public static function form(Form $form): Form
    {
        $tz = \App\Services\VehicleServiceBlockService::policyTimezone();

        return $form
            ->schema([
                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\Select::make('fleet_vehicle_id')
                            ->label('Vehicle')
                            ->relationship('vehicle', 'registration_number', fn (Builder $query) => $query->orderBy('registration_number'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?FleetVehicleServiceBlock $record) => $record !== null),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start')
                            ->timezone($tz)
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->disabled(fn (?FleetVehicleServiceBlock $record) => $record?->isCancelled() ?? false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End')
                            ->timezone($tz)
                            ->required()
                            ->native(false)
                            ->seconds(false)
                            ->after('starts_at')
                            ->disabled(fn (?FleetVehicleServiceBlock $record) => $record?->isCancelled() ?? false),
                        Forms\Components\Textarea::make('note')
                            ->rows(2)
                            ->maxLength(2000)
                            ->disabled(fn (?FleetVehicleServiceBlock $record) => $record?->isCancelled() ?? false),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tz = \App\Services\VehicleServiceBlockService::policyTimezone();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.registration_number')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Start')
                    ->dateTime()
                    ->timezone($tz)
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('End')
                    ->dateTime()
                    ->timezone($tz)
                    ->sortable(),
                Tables\Columns\TextColumn::make('cancelled_at')
                    ->label('Cancelled')
                    ->dateTime()
                    ->timezone($tz)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->timezone($tz)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('fleet_vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'registration_number')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('cancelled')
                    ->label('Cancelled')
                    ->nullable()
                    ->trueLabel('Yes')
                    ->falseLabel('No')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('cancelled_at'),
                        false: fn (Builder $q) => $q->whereNull('cancelled_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled()),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled() && $r->ends_at->isFuture())
                    ->action(function (FleetVehicleServiceBlock $record): void {
                        app(\App\Services\VehicleServiceBlockService::class)->cancel($record);
                    }),
                Tables\Actions\Action::make('complete_early')
                    ->label('End now')
                    ->color('warning')
                    ->icon('heroicon-o-stop')
                    ->requiresConfirmation()
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled() && $r->ends_at->isFuture())
                    ->action(function (FleetVehicleServiceBlock $record): void {
                        app(\App\Services\VehicleServiceBlockService::class)->completeEarly($record);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVehicleServiceBlocks::route('/'),
            'create' => Pages\CreateFleetVehicleServiceBlock::route('/create'),
            'edit' => Pages\EditFleetVehicleServiceBlock::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vehicle']);
    }
}
