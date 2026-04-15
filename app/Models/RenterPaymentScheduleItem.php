<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenterPaymentScheduleItem extends Model
{
    protected $fillable = [
        'renter_id',
        'payment_date',
        'amount',
        'is_paid',
        'is_overdue',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'is_paid' => 'boolean',
            'is_overdue' => 'boolean',
        ];
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }
}
