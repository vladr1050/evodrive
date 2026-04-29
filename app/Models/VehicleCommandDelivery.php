<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleCommandDelivery extends Model
{
    protected $fillable = [
        'car_command_id',
        'vehicle_id',
        'driver_id',
        'shift_id',
        'sequence',
        'requested_mode',
        'effective_transport',
        'sim_number',
        'command_text',
        'ok',
        'failure_code',
        'error_message',
        'provider_refs',
        'response_detail',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'provider_refs' => 'array',
        ];
    }

    public function carCommand(): BelongsTo
    {
        return $this->belongsTo(CarCommand::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
