<?php

namespace App\Models;

use App\Enums\PreferredLanguage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'preferred_language',
        'profile_photo',
        'is_active',
        'coach_status',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'preferred_language' => PreferredLanguage::class,
        ];
    }

    /** Relationships where this user is the coach. */
    public function coachRelationships(): HasMany
    {
        return $this->hasMany(CoachDiscipleRelationship::class, 'coach_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** A coach can only coach once an admin has approved the account. */
    public function isApprovedCoach(): bool
    {
        return $this->is_active
            && $this->coach_status === 'approved'
            && $this->hasRole('coach');
    }
}
