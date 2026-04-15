<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Renter extends Model
{
    protected $fillable = [
        'name_or_company',
        'personal_code_or_reg_number',
        'client_identifier',
        'licence',
        'is_active',
        'phone',
        'email',
        'contract_signed_at',
        'contract_ends_at',
        'total_debt',
        'next_payment_at',
        'overdue_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'contract_signed_at' => 'date',
            'contract_ends_at' => 'date',
            'total_debt' => 'decimal:2',
            'next_payment_at' => 'date',
            'overdue_days' => 'integer',
        ];
    }

    public function rentedFleetVehicles(): HasMany
    {
        return $this->hasMany(RentedFleetVehicle::class);
    }

    public function paymentScheduleItems(): HasMany
    {
        return $this->hasMany(RenterPaymentScheduleItem::class);
    }
}
