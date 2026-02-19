<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqItem extends Model
{
    protected $fillable = [
        'faq_category_id',
        'sort_order',
        'question',
        'answer',
    ];

    protected $casts = [
        'question' => 'array',
        'answer' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    public function getTranslatedQuestion(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $q = $this->question ?? [];

        return $q[$locale] ?? $q['en'] ?? null;
    }

    public function getTranslatedAnswer(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $a = $this->answer ?? [];

        return $a[$locale] ?? $a['en'] ?? null;
    }
}
