<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'shift_id',
        'action',
        'performed_by_type',
        'performed_by_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
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

    public static function logCreated(Shift $shift, string $performedByType = 'driver', ?int $performedById = null): self
    {
        return self::create([
            'driver_id' => $shift->driver_id,
            'shift_id' => $shift->id,
            'action' => 'created',
            'performed_by_type' => $performedByType,
            'performed_by_id' => $performedById,
        ]);
    }

    public static function logCancelled(Shift $shift, string $performedByType = 'driver', ?int $performedById = null): self
    {
        return self::create([
            'driver_id' => $shift->driver_id,
            'shift_id' => $shift->id,
            'action' => 'cancelled',
            'performed_by_type' => $performedByType,
            'performed_by_id' => $performedById,
        ]);
    }

    public static function logEdited(Shift $shift, string $performedByType = 'driver', ?int $performedById = null, array $meta = []): self
    {
        return self::create([
            'driver_id' => $shift->driver_id,
            'shift_id' => $shift->id,
            'action' => 'edited',
            'performed_by_type' => $performedByType,
            'performed_by_id' => $performedById,
            'meta' => $meta ?: null,
        ]);
    }
}
