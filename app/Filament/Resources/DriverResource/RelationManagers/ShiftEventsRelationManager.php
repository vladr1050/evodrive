<?php

namespace App\Filament\Resources\DriverResource\RelationManagers;

use App\Enums\ShiftStatus;
use App\Models\ShiftPolicy;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShiftEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'shiftEvents';

    protected static ?string $title = 'Shift history';

    protected static ?string $recordTitleAttribute = 'action';

    public function table(Table $table): Table
    {
        $tz = ShiftPolicy::active()?->timezone ?: 'Europe/Riga';

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['shift.vehicle', 'shift.station']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime()
                    ->timezone($tz)
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => 'Created',
                        'cancelled' => 'Cancelled',
                        'edited' => 'Edited',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'cancelled' => 'danger',
                        'edited' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('shift_id')
                    ->label('Shift')
                    ->formatStateUsing(fn ($record) => '#'.$record->shift_id),
                Tables\Columns\TextColumn::make('shift.status')
                    ->label('Shift status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ShiftStatus ? ucfirst($state->value) : '—')
                    ->color(fn ($state) => match (true) {
                        $state === ShiftStatus::Booked => 'warning',
                        $state === ShiftStatus::Completed => 'success',
                        $state === ShiftStatus::Cancelled => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shift.starts_at')
                    ->label('Shift start')
                    ->dateTime('Y-m-d H:i')
                    ->timezone($tz)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shift.ends_at')
                    ->label('Shift end')
                    ->dateTime('Y-m-d H:i')
                    ->timezone($tz)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shift.vehicle.registration_number')
                    ->label('Vehicle')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shift.station.name')
                    ->label('Station')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('performed_by_type')
                    ->label('Performed by')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
