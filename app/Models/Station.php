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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
