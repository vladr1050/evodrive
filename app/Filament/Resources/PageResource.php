<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource\RelationManagers\PageSectionsRelationManager;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessResource('pages') ?? false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Page')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->disabled()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ])->columns(2),
                Forms\Components\Tabs::make('Translations')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('English')
                            ->schema(static::localeSchema('en')),
                        Forms\Components\Tabs\Tab::make('Русский')
                            ->schema(static::localeSchema('ru')),
                        Forms\Components\Tabs\Tab::make('Latviešu')
                            ->schema(static::localeSchema('lv')),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Section::make('SEO (optional)')
                    ->schema([
                        Forms\Components\TextInput::make('canonical_url'),
                        Forms\Components\TextInput::make('robots')->default('index,follow'),
                        Forms\Components\FileUpload::make('og_image')
                            ->label('OG Image')
                            ->image()
                            ->disk('public')
                            ->directory('pages')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
                    ])
                    ->collapsed()
                    ->columns(2),
            ]);
    }

    protected static function localeSchema(string $locale): array
    {
        return [
            Forms\Components\TextInput::make("title.{$locale}")->label('Title'),
            Forms\Components\TextInput::make("slug.{$locale}")->label('Slug'),
            Forms\Components\TextInput::make("meta_title.{$locale}")->label('Meta Title')->columnSpanFull(),
            Forms\Components\Textarea::make("meta_description.{$locale}")->label('Meta Description')->rows(2)->columnSpanFull(),
            Forms\Components\TextInput::make("og_title.{$locale}")->label('OG Title (optional)')->columnSpanFull(),
            Forms\Components\Textarea::make("og_description.{$locale}")->label('OG Description (optional)')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->badge()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')->formatStateUsing(fn ($record) => $record->getTranslated('title') ?? '-')->limit(40)->wrap(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('key')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            PageSectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
