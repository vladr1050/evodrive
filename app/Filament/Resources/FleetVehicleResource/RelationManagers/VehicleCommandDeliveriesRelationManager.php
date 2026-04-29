<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use App\Models\ShiftPolicy;
use App\Models\VehicleCommandDelivery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleCommandDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'commandDeliveries';

    protected static ?string $title = 'Command delivery log';

    protected static ?string $recordTitleAttribute = 'command_text';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $tz = ShiftPolicy::active()?->timezone ?: 'Europe/Riga';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['carCommand', 'driver', 'shift']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->timezone($tz)
                    ->sortable(),
                Tables\Columns\TextColumn::make('carCommand.action')
                    ->label('Action')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sequence')
                    ->label('Step')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_mode')
                    ->label('Requested')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),
                Tables\Columns\TextColumn::make('effective_transport')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),
                Tables\Columns\TextColumn::make('sim_number')
                    ->label('SIM')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('command_text')
                    ->label('Command')
                    ->limit(40)
                    ->tooltip(fn (VehicleCommandDelivery $r) => $r->command_text),
                Tables\Columns\IconColumn::make('ok')
                    ->label('OK')
                    ->boolean(),
                Tables\Columns\TextColumn::make('failure_code')
                    ->label('Failure')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('response_detail')
                    ->label('Response')
                    ->limit(50)
                    ->tooltip(fn (VehicleCommandDelivery $r) => $r->response_detail)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
