<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Station $station) {
            if ($station->fleetVehicles()->exists() || $station->shifts()->exists()) {
                throw new \RuntimeException('Cannot delete station: it has linked vehicles or shifts.');
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'address',
        'latitude',
        'longitude',
        'provider',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Short label for dense UI (street / place without full provider dump).
     */
    public function shortLabel(): string
    {
        if ($this->address) {
            $street = trim(explode(',', $this->address)[0]);
            if ($street !== '') {
                return $street;
            }
        }

        $name = (string) $this->name;
        $provider = $this->resolvedProvider();
        if ($provider !== '' && str_starts_with($name, $provider)) {
            $rest = trim(substr($name, strlen($provider)));

            return $rest !== '' ? $rest : $name;
        }

        return $name;
    }

    /**
     * Provider for grouping; falls back to parsing common name prefixes.
     */
    public function resolvedProvider(): string
    {
        if (filled($this->provider)) {
            return (string) $this->provider;
        }

        $name = (string) $this->name;
        foreach (['Elektrum Drive', 'Elektrum', 'Eleport', 'Enefit', 'Ignitis'] as $prefix) {
            if (stripos($name, $prefix) === 0) {
                return $prefix;
            }
        }

        $parts = preg_split('/\s+/', $name, 3) ?: [];

        return $parts[0] ?? 'Other';
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function fleetVehicles(): HasMany
    {
        return $this->hasMany(FleetVehicle::class, 'home_station_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
