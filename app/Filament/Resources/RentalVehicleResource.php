<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\FileUpload as FileUploadField;
use App\Filament\Resources\RentalVehicleResource\Pages;
use App\Models\RentalVehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RentalVehicleResource extends Resource
{
    protected static ?string $model = RentalVehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Rental';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vehicle')
                    ->schema([
                        Forms\Components\TextInput::make('make')->required()->maxLength(100),
                        Forms\Components\TextInput::make('model')->required()->maxLength(100),
                        Forms\Components\TextInput::make('year')->required()->numeric()->minValue(2000)->maxValue(2030),
                        Forms\Components\TextInput::make('type')->required()->maxLength(50)->placeholder('Hybrid, Electric'),
                        Forms\Components\TextInput::make('transmission')->required()->maxLength(50)->default('Auto'),
                        Forms\Components\TextInput::make('consumption')->required()->maxLength(50)->placeholder('4.5L or 14kWh'),
                        Forms\Components\TextInput::make('seats')->numeric()->default(5)->minValue(1)->maxValue(9),
                        Forms\Components\TextInput::make('price')->required()->numeric()->integer()->minValue(0)->prefix('€')->suffix('/wk'),
                        Forms\Components\TextInput::make('deposit')->required()->numeric()->integer()->minValue(0)->prefix('€'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Image')
                    ->schema([
                        FileUploadField::make('image_path')
                            ->label('Upload image (overrides URL below)')
                            ->image()
                            ->disk('public')
                            ->directory('rental')
                            ->maxSize(2048)
                            ->imagePreviewHeight(200)
                            ->helperText('Optional. If empty, image_url is used.'),
                        Forms\Components\TextInput::make('image_url')
                            ->label('Or image URL')
                            ->url()
                            ->helperText('External image URL if no upload.'),
                    ])
                    ->collapsed(false),
                Forms\Components\Section::make('Categories')
                    ->schema([
                        Forms\Components\TagsInput::make('categories')
                            ->placeholder('Bolt, Forus, Economy')
                            ->helperText('Tags for Bolt, Forus, etc.'),
                    ])
                    ->collapsed(),
                Forms\Components\Tabs::make('Description')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('EN')
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('RU')
                            ->schema([
                                Forms\Components\Textarea::make('description.ru')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('LV')
                            ->schema([
                                Forms\Components\Textarea::make('description.lv')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower = first in list'),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label(__('Photo'))
                    ->disk('public')
                    ->defaultImageUrl(fn ($record) => $record->image_url)
                    ->width(80)
                    ->height(48)
                    ->extraImgAttributes(['loading' => 'lazy']),
                Tables\Columns\TextColumn::make('make')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('model')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('year')->sortable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('price')->money('EUR')->suffix('/wk'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListRentalVehicles::route('/'),
            'create' => Pages\CreateRentalVehicle::route('/create'),
            'edit' => Pages\EditRentalVehicle::route('/{record}/edit'),
        ];
    }
}
