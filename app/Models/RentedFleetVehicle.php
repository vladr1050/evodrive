<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentedFleetVehicle extends Model
{
    protected $fillable = [
        'renter_id',
        'label',
        'brand',
        'model',
        'year',
        'color',
        'atd_license_number',
        'registration_number',
        'status',
        'imei',
        'sim',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
        ];
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }
}
