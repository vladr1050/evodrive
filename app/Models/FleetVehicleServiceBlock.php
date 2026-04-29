<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetVehicleServiceBlock extends Model
{
    protected $table = 'fleet_vehicle_service_blocks';

    protected $fillable = [
        'fleet_vehicle_id',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'created_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isActiveAt(\Carbon\Carbon $momentUtc): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        return $this->starts_at->lt($momentUtc) && $this->ends_at->gt($momentUtc);
    }

    /**
     * Active = not cancelled and not ended yet (ends_at > now).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('cancelled_at')->where('ends_at', '>', now());
    }

    /**
     * Blocks that overlap [startUtc, endUtc) for availability (non-cancelled only).
     */
    public function scopeOverlappingWindow($query, \Carbon\Carbon $startUtc, \Carbon\Carbon $endUtc)
    {
        return $query->whereNull('cancelled_at')
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc);
    }
}
