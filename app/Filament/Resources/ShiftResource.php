<?php

namespace App\Filament\Resources;

use App\Enums\ShiftStatus;
use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('duration_hours')
                    ->label('Duration (h)')
                    ->state(fn (Shift $r) => number_format($r->durationHours(), 1)),
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('Driver')
                    ->formatStateUsing(fn (Shift $r) => $r->driver ? $r->driver->name . ' (' . $r->driver->email . ')' : '-')
                    ->searchable(query: function (Builder $q, string $search) {
                        $q->whereHas('driver', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle.registration_number')
                    ->label('Vehicle')
                    ->formatStateUsing(fn (Shift $r) => $r->vehicle ? $r->vehicle->registration_number . ' – ' . trim(($r->vehicle->brand ?? '') . ' ' . ($r->vehicle->model ?? '')) : '-')
                    ->searchable(query: function (Builder $q, string $search) {
                        $q->whereHas('vehicle', fn ($q) => $q->where('registration_number', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('station.name')
                    ->label('Station')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        ShiftStatus::Booked => 'Confirmed',
                        ShiftStatus::Completed => 'Completed',
                        ShiftStatus::Cancelled => 'Cancelled',
                        default => $state instanceof ShiftStatus ? $state->value : (string) $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        ShiftStatus::Booked => 'success',
                        ShiftStatus::Completed => 'gray',
                        ShiftStatus::Cancelled => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('starts_at')
                    ->form([
                        Forms\Components\DatePicker::make('starts_from')->label('From'),
                        Forms\Components\DatePicker::make('starts_until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['starts_from'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '>=', $d))
                        ->when($data['starts_until'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '<=', $d))
                    ),
                Tables\Filters\SelectFilter::make('station_id')
                    ->label('Station')
                    ->relationship('station', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'registration_number')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'email')
                    ->getOptionLabelFromRecordUsing(fn (Model $r) => $r->name . ' (' . $r->email . ')')
                    ->searchable(['first_name', 'last_name', 'email']),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ShiftStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel shift')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel this shift?')
                    ->visible(fn (Shift $record) => $record->status === ShiftStatus::Booked && $record->starts_at->isFuture())
                    ->action(function (Shift $record): void {
                        $record->update([
                            'status' => ShiftStatus::Cancelled,
                            'cancelled_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Shift cancelled')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
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
}
