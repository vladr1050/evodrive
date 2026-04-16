<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RenterContractDocument extends Model
{
    /** @use HasFactory<\Database\Factories\RenterContractDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'renter_id',
        'description',
        'stored_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (RenterContractDocument $document) {
            if ($document->stored_path && self::isStoredPathAllowedForRenter($document->stored_path, $document->renter_id)) {
                Storage::disk('renter_contracts')->delete($document->stored_path);
            }
        });
    }

    /**
     * Prevent path traversal and cross-renter access if DB is tampered with.
     */
    public static function isStoredPathAllowedForRenter(string $storedPath, int $renterId): bool
    {
        $prefix = $renterId.'/';

        if (! str_starts_with($storedPath, $prefix)) {
            return false;
        }

        $rest = substr($storedPath, strlen($prefix));
        if ($rest === '' || str_contains($rest, '..') || str_contains($rest, '\\')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_\-\.]+\.[A-Za-z0-9]{1,8}$/', $rest);
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function safeDownloadFilename(): string
    {
        $ext = pathinfo($this->stored_path, PATHINFO_EXTENSION) ?: 'bin';
        $base = \Illuminate\Support\Str::slug($this->description) ?: 'document';

        return $base.'.'.strtolower($ext);
    }
}
