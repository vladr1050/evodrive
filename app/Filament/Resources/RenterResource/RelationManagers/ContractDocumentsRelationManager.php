<?php

namespace App\Filament\Resources\RenterResource\RelationManagers;

use App\Filament\Forms\Components\FileUpload as FileUploadField;
use App\Models\RenterContractDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ContractDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'contractDocuments';

    protected static ?string $title = 'Contract documents';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->label('Document name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown in the list and used as the download file name.'),
                FileUploadField::make('stored_path')
                    ->label('File')
                    ->required()
                    ->visibleOn('create')
                    ->disk('renter_contracts')
                    ->directory(fn () => (string) $this->ownerRecord->getKey())
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->maxSize(15 * 1024)
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file): string {
                        $ext = strtolower($file->getClientOriginalExtension());
                        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
                        if (! in_array($ext, $allowed, true)) {
                            $ext = 'bin';
                        }

                        return (string) Str::uuid().'.'.$ext;
                    })
                    ->helperText('PDF, Word, or images. Max 15 MB. Stored on the server; only staff with Rental access can download.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('uploadedBy'))
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 1).' KB')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['renter_id'] = $this->ownerRecord->getKey();
                        $data['uploaded_by_id'] = auth()->id();

                        $disk = Storage::disk('renter_contracts');
                        if (! empty($data['stored_path']) && $disk->exists($data['stored_path'])) {
                            $data['size_bytes'] = $disk->size($data['stored_path']);
                            try {
                                $data['mime_type'] = $disk->mimeType($data['stored_path']) ?: 'application/octet-stream';
                            } catch (\Throwable) {
                                $data['mime_type'] = 'application/octet-stream';
                            }
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (RenterContractDocument $record): string => route('admin.renter-contract-documents.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        unset($data['stored_path']);

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
