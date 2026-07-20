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
        'telegram_id',
        'atd_number',
        'license_number',
        'locale',
        'status',
        'portal_preferences',
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
            'portal_preferences' => 'array',
        ];
    }

    /**
     * @return list<int>
     */
    public function favoriteStationIds(): array
    {
        $ids = $this->portal_preferences['favorite_station_ids'] ?? [];

        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    /**
     * @return list<int>
     */
    public function recentStationIds(): array
    {
        $ids = $this->portal_preferences['recent_station_ids'] ?? [];

        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    public function toggleFavoriteStation(int $stationId): void
    {
        $prefs = $this->portal_preferences ?? [];
        $favorites = $this->favoriteStationIds();
        if (in_array($stationId, $favorites, true)) {
            $favorites = array_values(array_filter($favorites, fn (int $id) => $id !== $stationId));
        } else {
            array_unshift($favorites, $stationId);
            $favorites = array_values(array_unique($favorites));
        }
        $prefs['favorite_station_ids'] = $favorites;
        $this->portal_preferences = $prefs;
        $this->save();
    }

    public function rememberRecentStation(int $stationId): void
    {
        $recent = $this->recentStationIds();
        if (($recent[0] ?? null) === $stationId) {
            return;
        }
        $prefs = $this->portal_preferences ?? [];
        $recent = array_values(array_filter(
            $recent,
            fn (int $id) => $id !== $stationId
        ));
        array_unshift($recent, $stationId);
        $prefs['recent_station_ids'] = array_slice($recent, 0, 8);
        $this->portal_preferences = $prefs;
        $this->save();
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

    public function shiftEvents(): HasMany
    {
        return $this->hasMany(ShiftEvent::class)->orderByDesc('created_at');
    }
}
