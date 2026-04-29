<?php

namespace App\Models;

use App\Enums\VehicleCommandTransport;
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
        'imei',
        'sim',
        'command_transport',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'command_transport' => VehicleCommandTransport::class,
        ];
    }

    /**
     * Per-vehicle channel (sms | gprs | auto). Null uses fleet default from config.
     */
    public function effectiveCommandTransport(): string
    {
        $fallback = strtolower((string) config('car_control.default_transport', 'sms'));
        if (! in_array($fallback, ['sms', 'gprs', 'auto'], true)) {
            $fallback = 'sms';
        }

        $v = $this->command_transport;
        if ($v instanceof VehicleCommandTransport) {
            return $v->value;
        }

        return $fallback;
    }

    public function homeStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'home_station_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'vehicle_id');
    }

    public function commandDeliveries(): HasMany
    {
        return $this->hasMany(VehicleCommandDelivery::class, 'vehicle_id')->orderByDesc('created_at');
    }
}
