<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Models\RentalVehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\TextInput::make('phone')->tel()->required()->maxLength(30),
                        Forms\Components\TextInput::make('name')->maxLength(255),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    ])->columns(2),
                Forms\Components\Section::make('Qualification')
                    ->schema([
                        Forms\Components\Select::make('intent')->options(['work' => 'Work', 'rent' => 'Rent']),
                        Forms\Components\Select::make('rent_car_id')
                            ->label('Rental Vehicle')
                            ->options(
                                fn () => RentalVehicle::where('is_active', true)->orderBy('make')->get()
                                    ->mapWithKeys(fn (RentalVehicle $r) => [$r->id => $r->make . ' ' . $r->model . ' (€' . $r->price . __('ui.rent_price_week_suffix') . ')'])
                                    ->all()
                            )
                            ->searchable()
                            ->getOptionLabelUsing(fn ($value): ?string => $value ? (($r = RentalVehicle::find($value)) ? $r->make . ' ' . $r->model . ' (€' . $r->price . __('ui.rent_price_week_suffix') . ')' : '—') : null),
                        Forms\Components\Toggle::make('atd_license')->label('ATD License'),
                        Forms\Components\TextInput::make('atd_number')->label('ATD Card Number')->maxLength(50),
                        Forms\Components\TextInput::make('driving_experience')->maxLength(20),
                        Forms\Components\TextInput::make('area')->maxLength(100),
                    ])->columns(2),
                Forms\Components\Section::make('Status & Source')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(array_combine(Lead::STATUSES, array_map(fn ($s) => ucfirst($s), Lead::STATUSES)))
                            ->required(),
                        Forms\Components\TextInput::make('source'),
                        Forms\Components\Section::make('UTM')
                            ->schema([
                                Forms\Components\TextInput::make('utm_source'),
                                Forms\Components\TextInput::make('utm_campaign'),
                                Forms\Components\TextInput::make('utm_medium'),
                                Forms\Components\TextInput::make('utm_content'),
                                Forms\Components\TextInput::make('utm_term'),
                            ])
                            ->columns(2)
                            ->collapsed(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('intent')->badge()->color(fn ($state) => match ($state) {
                    'work' => 'success', 'rent' => 'info', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('rentalVehicle.make')
                    ->label('Rental Car')
                    ->formatStateUsing(fn ($record) => $record->rentalVehicle ? $record->rentalVehicle->make . ' ' . $record->rentalVehicle->model : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')->badge()->color(fn ($state) => match ($state) {
                    'google' => 'success', 'meta' => 'info', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'new' => 'primary', 'contacted' => 'info', 'approved' => 'success', 'rejected' => 'danger', 'archived' => 'gray',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('intent')->options(['work' => 'Work', 'rent' => 'Rent']),
                Tables\Filters\SelectFilter::make('status')->options(array_combine(Lead::STATUSES, array_map(fn ($s) => ucfirst($s), Lead::STATUSES))),
                Tables\Filters\SelectFilter::make('source')->options(['google' => 'Google', 'meta' => 'Meta', 'unknown' => 'Unknown']),
                Tables\Filters\Filter::make('created_at')->form([
                    Forms\Components\DatePicker::make('created_from')->label('From'),
                    Forms\Components\DatePicker::make('created_until')->label('Until'),
                ])->query(fn (Builder $q, array $data) => $q
                    ->when($data['created_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['created_until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('status_new')->label('Mark New')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'new']))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('status_contacted')->label('Mark Contacted')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'contacted']))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('status_approved')->label('Mark Approved')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'approved']))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('status_rejected')->label('Mark Rejected')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'rejected']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
