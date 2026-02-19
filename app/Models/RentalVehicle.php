<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RentalVehicle extends Model
{
    protected $fillable = [
        'make', 'model', 'year', 'type', 'transmission', 'consumption',
        'seats', 'price', 'deposit', 'image_path', 'image_url', 'categories', 'description',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'categories' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'year' => 'integer',
        'seats' => 'integer',
        'price' => 'integer',
        'deposit' => 'integer',
        'sort_order' => 'integer',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            $path = Storage::disk('public')->url(ltrim($this->image_path, '/'));
            return asset($path);
        }
        $url = $this->attributes['image_url'] ?? null;
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http://localhost') || str_starts_with($url, 'https://localhost')) {
            $path = parse_url($url, PHP_URL_PATH);
            return $path ? asset($path) : $url;
        }
        if (! str_starts_with($url, 'http')) {
            return asset($url);
        }
        return $url;
    }

    public function getTranslatedDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $desc = $this->description ?? [];
        return $desc[$locale] ?? $desc['en'] ?? null;
    }

    public function toPublicArray(): array
    {
        return [
            'id' => (string) $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'type' => $this->type,
            'transmission' => $this->transmission,
            'consumption' => $this->consumption,
            'seats' => $this->seats,
            'price' => $this->price,
            'deposit' => $this->deposit,
            'image' => $this->image_url ?? '',
            'categories' => $this->categories ?? [],
            'description' => $this->getTranslatedDescription(),
        ];
    }
}
