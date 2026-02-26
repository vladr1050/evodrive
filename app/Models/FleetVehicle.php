<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetVehicle extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (FleetVehicle $vehicle) {
            if ($vehicle->shifts()->exists()) {
                throw new \RuntimeException('Cannot delete vehicle: it has linked shifts.');
            }
        });
    }

    protected $fillable = [
        'label',
        'brand',
        'model',
        'year',
        'color',
        'atd_license_number',
        'registration_number',
        'home_station_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
        ];
    }

    public function homeStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'home_station_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'vehicle_id');
    }
}
