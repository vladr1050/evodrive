<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqCategory extends Model
{
    protected $fillable = [
        'slug',
        'sort_order',
        'title',
    ];

    protected $casts = [
        'title' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class)->orderBy('sort_order');
    }

    public function getTranslatedTitle(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $title = $this->title ?? [];

        return $title[$locale] ?? $title['en'] ?? null;
    }
}
