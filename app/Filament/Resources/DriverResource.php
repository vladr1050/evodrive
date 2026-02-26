<?php

namespace App\Filament\Resources;

use App\Enums\DriverStatus;
use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Fleet Management';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('fleet_management') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('license_number')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\Select::make('locale')
                            ->options(['en' => 'English', 'ru' => 'Russian', 'lv' => 'Latvian'])
                            ->default('en')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options(collect(DriverStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                            ->default(DriverStatus::Active)
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Password')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->confirmed()
                            ->revealable(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->label('Confirm password')
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->dehydrated(false)
                            ->revealable(),
                    ])
                    ->columns(2)
                    ->visible(fn (string $operation) => $operation === 'create' || request()->has('change_password')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn (Driver $r) => $r->name)
                    ->searchable(query: function (Builder $q, string $search) {
                        $q->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $q, string $column, string $direction) {
                        $q->orderBy('first_name', $direction)->orderBy('last_name', $direction);
                    }),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('license_number')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof DriverStatus ? ucfirst($state->value) : (string) $state)
                    ->color(fn ($state) => match (true) {
                        $state === DriverStatus::Active => 'success',
                        $state === DriverStatus::Suspended => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('locale')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('first_name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DriverStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->confirmed()
                            ->revealable(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->label('Confirm password')
                            ->required()
                            ->dehydrated(false)
                            ->revealable(),
                    ])
                    ->action(function (Driver $record, array $data): void {
                        $record->update(['password' => Hash::make($data['password'])]);
                        \Filament\Notifications\Notification::make()
                            ->title('Password updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->action(fn ($records) => $records->each->update(['status' => DriverStatus::Active]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('suspend')
                        ->action(fn ($records) => $records->each->update(['status' => DriverStatus::Suspended]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
