<?php

namespace App\Filament\Resources;

use App\Enums\VehicleStatus;
use App\Filament\Resources\RentedFleetVehicleResource\Pages;
use App\Models\RentedFleetVehicle;
use App\Models\Renter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RentedFleetVehicleResource extends Resource
{
    protected static ?string $model = RentedFleetVehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Rental';

    protected static ?string $navigationLabel = 'Rented vehicles';

    protected static ?string $modelLabel = 'Rented vehicle';

    protected static ?string $pluralModelLabel = 'Rented vehicles';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('rental_vehicles') ?? false;
    }

    public static function form(Form $form): Form
    {
        $currentYear = (int) date('Y');

        return $form
            ->schema([
                Forms\Components\Section::make('Renter')
                    ->schema([
                        Forms\Components\Select::make('renter_id')
                            ->label('Renter')
                            ->relationship(
                                'renter',
                                'name_or_company',
                                fn (Builder $query) => $query->orderBy('name_or_company')
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->getOptionLabelFromRecordUsing(fn (Renter $r) => $r->name_or_company.($r->phone ? ' — '.$r->phone : '')),
                    ]),
                Forms\Components\Section::make('Vehicle')
                    ->description('Same fields as Fleet vehicles, without home station.')
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
                        Forms\Components\Select::make('status')
                            ->options(collect(VehicleStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)]))
                            ->default(VehicleStatus::Active)
                            ->required(),
                        Forms\Components\TextInput::make('imei')
                            ->label('IMEI')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('sim')
                            ->label('SIM')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('37120000000'),
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
                Tables\Columns\TextColumn::make('brand_model')
                    ->label('Brand / model')
                    ->getStateUsing(fn (RentedFleetVehicle $r) => trim(($r->brand ?? '').' '.($r->model ?? '')))
                    ->searchable(query: function (Builder $q, string $search) {
                        $search = trim($search);
                        if ($search === '') {
                            return;
                        }
                        $q->where(function (Builder $sub) use ($search) {
                            $sub->where('brand', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('renter.name_or_company')
                    ->label('Renter')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('renter.phone')
                    ->label('Renter phone')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('renter.email')
                    ->label('Renter e-mail')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('renter_id')
                    ->label('Renter')
                    ->relationship('renter', 'name_or_company')
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
            'index' => Pages\ListRentedFleetVehicles::route('/'),
            'create' => Pages\CreateRentedFleetVehicle::route('/create'),
            'edit' => Pages\EditRentedFleetVehicle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('renter');
    }
}
