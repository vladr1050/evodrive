<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\FileUpload as BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class FileUpload extends BaseFileUpload
{
    /**
     * Return file info including preview URL for temporary uploads so FilePond can show the image.
     *
     * @return array<array{name: string, size: int, type: string, url: string} | null> | null
     */
    public function getUploadedFiles(): ?array
    {
        $urls = [];

        foreach ($this->getState() ?? [] as $fileKey => $file) {
            if ($file instanceof TemporaryUploadedFile) {
                try {
                    if (! $file->isPreviewable()) {
                        $urls[$fileKey] = null;
                        continue;
                    }
                    $fullUrl = $file->temporaryUrl();
                    $url = $this->relativeUrl($fullUrl);
                    $urls[$fileKey] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'url' => $url,
                    ];
                } catch (Throwable) {
                    $urls[$fileKey] = null;
                }
                continue;
            }

            $callback = $this->getUploadedFileUsing;

            if (! $callback) {
                return [$fileKey => null];
            }

            $urls[$fileKey] = $this->evaluate($callback, [
                'file' => $file,
                'storedFileNames' => $this->getStoredFileNames(),
            ]) ?: null;
        }

        return $urls;
    }

    /**
     * Convert absolute URL to relative so preview works when APP_URL differs from request host.
     */
    private function relativeUrl(string $fullUrl): string
    {
        $path = parse_url($fullUrl, PHP_URL_PATH);
        $query = parse_url($fullUrl, PHP_URL_QUERY);

        return $path . ($query ? '?' . $query : '');
    }
}
