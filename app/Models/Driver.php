<?php

namespace App\Models;

use App\Enums\DriverStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Driver extends Authenticatable
{
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (Driver $driver) {
            if ($driver->shifts()->exists()) {
                throw new \RuntimeException('Cannot delete driver: they have linked shifts.');
            }
        });
    }

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'atd_number',
        'license_number',
        'locale',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => DriverStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        if (isset($this->attributes['first_name']) || isset($this->attributes['last_name'])) {
            return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        }
        return (string) ($this->attributes['name'] ?? '');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
