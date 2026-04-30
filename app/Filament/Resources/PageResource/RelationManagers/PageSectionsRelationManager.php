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

    /**
     * Pages whose sections use heading + Markdown body (legal CMS).
     * Other pages keep KeyValue for flexible keys (e.g. hero h1, items).
     *
     * @return list<string>
     */
    protected static function legalStylePageKeys(): array
    {
        return ['privacy', 'terms'];
    }

    /**
     * @return list<string>
     */
    protected static function markdownToolbarButtons(): array
    {
        return [
            'bold',
            'italic',
            'strike',
            'heading',
            'bulletList',
            'orderedList',
            'blockquote',
            'link',
            'table',
            'undo',
            'redo',
        ];
    }

    public function form(Form $form): Form
    {
        $pageKey = $this->getOwnerRecord()->key;
        $isLegalStyle = in_array($pageKey, self::legalStylePageKeys(), true);

        $keyField = Forms\Components\TextInput::make('key')
            ->required()
            ->maxLength(255)
            ->disabledOn('edit');

        if ($isLegalStyle) {
            return $form
                ->schema([
                    $keyField,
                    Forms\Components\Tabs::make('Content')
                        ->tabs([
                            Forms\Components\Tabs\Tab::make('EN')
                                ->schema(self::legalLocaleTabSchema(
                                    'en',
                                    'Section heading (optional)',
                                    'Main text',
                                    'Use the toolbar: bold, headings for structure, Table for grids. Stored as Markdown; the site renders it with the same rules as GitHub-style Markdown.',
                                )),
                            Forms\Components\Tabs\Tab::make('RU')
                                ->schema(self::legalLocaleTabSchema(
                                    'ru',
                                    'Заголовок раздела (необязательно)',
                                    'Основной текст',
                                    'Панель: жирный, заголовки для структуры, «Table» для таблиц. Сохраняется как Markdown; на сайте отображается как обычный GFM.',
                                )),
                            Forms\Components\Tabs\Tab::make('LV')
                                ->schema(self::legalLocaleTabSchema(
                                    'lv',
                                    'Sadaļas virsraksts (neobligāts)',
                                    'Galvenais teksts',
                                    'Rīkjosla: treknraksts, virsraksti struktūrai, «Table» tabulām. Saglabāts kā Markdown; vietnē attēlots kā GFM.',
                                )),
                        ])
                        ->columnSpanFull(),
                ]);
        }

        return $form
            ->schema([
                $keyField,
                Forms\Components\Tabs::make('Content')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('EN')
                            ->schema([
                                Forms\Components\KeyValue::make('content.en')
                                    ->keyLabel('Field')
                                    ->valueLabel('Value')
                                    ->reorderable()
                                    ->helperText('Arbitrary keys per locale (e.g. h1, items as JSON). For legal pages (privacy/terms), use the structured editor there instead.'),
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

    /**
     * @return array<Forms\Components\Component>
     */
    protected static function legalLocaleTabSchema(string $locale, string $headingLabel, string $bodyLabel, string $helper): array
    {
        return [
            Forms\Components\TextInput::make("content.{$locale}.heading")
                ->label($headingLabel)
                ->maxLength(500),
            Forms\Components\MarkdownEditor::make("content.{$locale}.body")
                ->label($bodyLabel)
                ->toolbarButtons(self::markdownToolbarButtons())
                ->helperText($helper)
                ->columnSpanFull(),
        ];
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
