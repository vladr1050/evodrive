<?php

namespace App\Filament\Resources\FaqCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FaqItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Tabs::make('Question & Answer')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('EN')
                            ->schema([
                                Forms\Components\TextInput::make('question.en')->label('Question')->required()->maxLength(500),
                                Forms\Components\Textarea::make('answer.en')->label('Answer')->required()->rows(4),
                            ]),
                        Forms\Components\Tabs\Tab::make('RU')
                            ->schema([
                                Forms\Components\TextInput::make('question.ru')->label('Вопрос')->maxLength(500),
                                Forms\Components\Textarea::make('answer.ru')->label('Ответ')->rows(4),
                            ]),
                        Forms\Components\Tabs\Tab::make('LV')
                            ->schema([
                                Forms\Components\TextInput::make('question.lv')->label('Jautājums')->maxLength(500),
                                Forms\Components\Textarea::make('answer.lv')->label('Atbilde')->rows(4),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('question')
                    ->formatStateUsing(fn ($record) => $record->getTranslatedQuestion() ?? '-')
                    ->limit(50)
                    ->wrap(),
            ])
            ->defaultSort('sort_order')
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
            ]);
    }
}
