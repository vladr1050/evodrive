<?php

namespace App\Filament\Resources\PageResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PageSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')->required()->disabled()->maxLength(255),
                Forms\Components\Tabs::make('Content')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('EN')
                            ->schema([
                                Forms\Components\KeyValue::make('content.en')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->reorderable()
                                    ->helperText('Use key "items" with value as JSON array for lists, e.g. ["Item 1","Item 2"]'),
                            ]),
                        Forms\Components\Tabs\Tab::make('RU')
                            ->schema([
                                Forms\Components\KeyValue::make('content.ru')
                                    ->keyLabel('Поле')
                                    ->valueLabel('Значение')
                                    ->reorderable(),
                            ]),
                        Forms\Components\Tabs\Tab::make('LV')
                            ->schema([
                                Forms\Components\KeyValue::make('content.lv')
                                    ->keyLabel('Lauks')
                                    ->valueLabel('Vērtība')
                                    ->reorderable(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')->badge(),
                Tables\Columns\TextColumn::make('sort_order'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
