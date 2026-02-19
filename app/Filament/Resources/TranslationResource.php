<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationResource\Pages;
use App\Models\Translation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('group')
                    ->required()
                    ->maxLength(50)
                    ->disabled(),
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->disabled(),
                Forms\Components\Tabs::make('Locales')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('English')
                            ->schema([
                                Forms\Components\Textarea::make('en')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Русский')
                            ->schema([
                                Forms\Components\Textarea::make('ru')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Latviešu')
                            ->schema([
                                Forms\Components\Textarea::make('lv')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('key')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('en')->limit(40)->wrap()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('ru')->limit(40)->wrap()->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lv')->limit(40)->wrap()->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('group')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options(['ui' => 'UI', 'apply' => 'Apply'])
                    ->default('ui'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTranslations::route('/'),
            'edit' => Pages\EditTranslation::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Translation::count();
    }
}
