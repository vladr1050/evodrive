<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftVehicleReplacement extends Model
{
    protected $fillable = [
        'batch_id',
        'shift_id',
        'from_vehicle_id',
        'to_vehicle_id',
        'created_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'batch_id' => 'string',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function fromVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'from_vehicle_id');
    }

    public function toVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'to_vehicle_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
