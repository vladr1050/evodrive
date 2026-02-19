<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'source', 'intent', 'rent_car_id', 'phone', 'name', 'email', 'atd_license', 'atd_number',
        'driving_experience', 'shift_preference', 'area',
        'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term',
        'status', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'atd_license' => 'boolean',
    ];

    public const STATUSES = ['new', 'contacted', 'approved', 'rejected', 'archived'];
    public const SOURCES = ['google', 'meta', 'unknown'];
    public const INTENTS = ['work', 'rent'];

    public function rentalVehicle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\RentalVehicle::class, 'rent_car_id');
    }
}
