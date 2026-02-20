<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'allowed_resources',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allowed_resources' => 'array',
        ];
    }

    /**
     * Permission keys for manager access (resource slugs used in canAccess).
     */
    public static function resourcePermissionKeys(): array
    {
        return [
            'leads' => 'Leads',
            'rental_vehicles' => 'Rental vehicles',
            'pages' => 'Pages',
            'translations' => 'Translations',
            'faq_categories' => 'FAQ categories',
            'site_settings' => 'Site settings',
        ];
    }

    public function canAccessResource(string $key): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        $allowed = $this->allowed_resources;
        // Legacy: manager with never-set permissions (null) had full access before the feature
        if ($allowed === null) {
            return true;
        }

        return in_array($key, $allowed, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role ?? 'manager', ['admin', 'manager']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
