<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarCommand extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const ACTION_START_SHIFT = 'start_shift';

    public const ACTION_OPEN_CAR = 'open_car';

    public const ACTION_CLOSE_CAR = 'close_car';

    public const ACTION_END_SHIFT = 'end_shift';

    protected $fillable = [
        'driver_id',
        'shift_id',
        'vehicle_id',
        'action',
        'sms_to',
        'sms_payloads',
        'status',
        'provider_message_ids',
        'transport_meta',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sms_payloads' => 'array',
            'provider_message_ids' => 'array',
            'transport_meta' => 'array',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }
}
