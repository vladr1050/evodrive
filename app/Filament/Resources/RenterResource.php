<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RenterResource\Pages;
use App\Models\RentedFleetVehicle;
use App\Models\Renter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RenterResource extends Resource
{
    protected static ?string $model = Renter::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Rental';

    protected static ?string $navigationLabel = 'Renters';

    protected static ?string $modelLabel = 'Renter';

    protected static ?string $pluralModelLabel = 'Renters';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('rental_vehicles') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client profile')
                    ->schema([
                        Forms\Components\TextInput::make('name_or_company')
                            ->label('Name / company')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('personal_code_or_reg_number')
                            ->label('Personal ID / reg. no.')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('client_identifier')
                            ->label('Client ID')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('licence')
                            ->label('License')
                            ->maxLength(100),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Contract')
                    ->schema([
                        Forms\Components\DatePicker::make('contract_signed_at')
                            ->label('Signed on')
                            ->native(false),
                        Forms\Components\DatePicker::make('contract_ends_at')
                            ->label('Ends on')
                            ->native(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Rented vehicles')
                    ->description('Choose vehicles from the Rented vehicles list (unassigned or already linked to this renter).')
                    ->schema([
                        Forms\Components\Select::make('rented_fleet_vehicle_ids')
                            ->label('Rented vehicles')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (?Model $record) {
                                $q = RentedFleetVehicle::query()
                                    ->orderBy('registration_number')
                                    ->orderBy('brand');

                                $q->where(function (Builder $sub) use ($record) {
                                    $sub->whereNull('renter_id');
                                    if ($record) {
                                        $sub->orWhere('renter_id', $record->id);
                                    }
                                });

                                return $q->get()->mapWithKeys(fn (RentedFleetVehicle $v) => [
                                    $v->id => $v->registration_number.' — '.trim(($v->brand ?? '').' '.($v->model ?? '')),
                                ]);
                            })
                            ->dehydrated(false),
                    ]),
                Forms\Components\Section::make('Payment schedule — summary')
                    ->schema([
                        Forms\Components\TextInput::make('total_debt')
                            ->label('Total debt')
                            ->numeric()
                            ->prefix('€')
                            ->nullable(),
                        Forms\Components\DatePicker::make('next_payment_at')
                            ->label('Next payment')
                            ->native(false),
                        Forms\Components\TextInput::make('overdue_days')
                            ->label('Days overdue')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(32767)
                            ->nullable(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Payment schedule')
                    ->schema([
                        Forms\Components\Repeater::make('paymentScheduleItems')
                            ->relationship()
                            ->schema([
                                Forms\Components\DatePicker::make('payment_date')
                                    ->label('Date')
                                    ->required()
                                    ->native(false),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('€'),
                                Forms\Components\Toggle::make('is_paid')
                                    ->label('Paid')
                                    ->default(false),
                                Forms\Components\Toggle::make('is_overdue')
                                    ->label('Overdue')
                                    ->default(false),
                            ])
                            ->columns(4)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['payment_date'], $state['amount'])
                                ? ($state['payment_date'].' — €'.$state['amount'])
                                : null),
                    ]),
                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_or_company')
                    ->label('Name / company')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_identifier')
                    ->label('Client ID')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('contract_ends_at')
                    ->label('Contract until')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle_regs')
                    ->label('Vehicles')
                    ->getStateUsing(
                        fn (Renter $record): string => $record->rentedFleetVehicles
                            ->pluck('registration_number')
                            ->filter()
                            ->join(', ') ?: '—'
                    ),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name_or_company')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
            'index' => Pages\ListRenters::route('/'),
            'create' => Pages\CreateRenter::route('/create'),
            'edit' => Pages\EditRenter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['rentedFleetVehicles:id,renter_id,registration_number']);
    }
}
