<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    protected $fillable = ['page_id', 'key', 'content', 'sort_order'];

    protected $casts = [
        'content' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get translated content for current locale.
     */
    public function getContentForLocale(?string $locale = null): array
    {
        $content = $this->content ?? [];
        $locale = $locale ?? app()->getLocale();

        if (isset($content[$locale])) {
            return $content[$locale];
        }

        return $content['en'] ?? (is_array($content) ? reset($content) : []);
    }

    /**
     * Get single field from translated content.
     */
    public function getField(string $field, ?string $locale = null): ?string
    {
        $data = $this->getContentForLocale($locale);
        return is_array($data) ? ($data[$field] ?? null) : null;
    }
}
