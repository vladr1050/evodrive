<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use App\Filament\Resources\FleetVehicleServiceBlockResource;
use App\Models\FleetVehicleServiceBlock;
use App\Services\VehicleServiceBlockService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FleetVehicleServiceBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceBlocks';

    protected static ?string $title = 'Service / maintenance';

    protected static ?string $recordTitleAttribute = 'starts_at';

    public function table(Table $table): Table
    {
        $tz = VehicleServiceBlockService::policyTimezone();

        return $table
            ->columns([
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
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('schedule_service')
                    ->label('Schedule service')
                    ->url(fn (): string => FleetVehicleServiceBlockResource::getUrl('create', [
                        'fleet_vehicle_id' => $this->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_block')
                    ->label('Edit')
                    ->url(fn (FleetVehicleServiceBlock $record): string => FleetVehicleServiceBlockResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled()),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled() && $r->ends_at->isFuture())
                    ->action(fn (FleetVehicleServiceBlock $r) => app(VehicleServiceBlockService::class)->cancel($r)),
                Tables\Actions\Action::make('complete_early')
                    ->label('End now')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (FleetVehicleServiceBlock $r) => ! $r->isCancelled() && $r->ends_at->isFuture())
                    ->action(fn (FleetVehicleServiceBlock $r) => app(VehicleServiceBlockService::class)->completeEarly($r)),
            ])
            ->bulkActions([]);
    }
}
