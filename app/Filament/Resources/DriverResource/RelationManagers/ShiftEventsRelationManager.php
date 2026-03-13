<?php

namespace App\Filament\Resources\DriverResource\RelationManagers;

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
            ->modifyQueryUsing(fn ($query) => $query->with('shift'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
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
                    ->formatStateUsing(function ($record) {
                        $shift = $record->shift;
                        if (! $shift) {
                            return '#' . $record->shift_id;
                        }
                        $starts = $shift->starts_at->setTimezone(ShiftPolicy::active()?->timezone ?: 'Europe/Riga')->format('d M Y H:i');
                        return '#' . $record->shift_id . ' — ' . $starts;
                    }),
                Tables\Columns\TextColumn::make('performed_by_type')
                    ->label('Performed by')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('viewShift')
                    ->label('View shift')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => \App\Filament\Resources\ShiftResource::getUrl('index', ['tableFilters' => ['driver_id' => $record->driver_id]]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}
