<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'station_id',
        'starts_at',
        'ends_at',
        'status',
        'confirmed_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by_driver_id',
        'cancellation_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ShiftStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_notified_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /** Driver who cancelled this shift (when status is Cancelled). */
    public function cancelledByDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'cancelled_by_driver_id');
    }

    public function durationHours(): float
    {
        return $this->starts_at->diffInMinutes($this->ends_at) / 60;
    }

    public function shiftEvents(): HasMany
    {
        return $this->hasMany(ShiftEvent::class)->orderByDesc('created_at');
    }
}
