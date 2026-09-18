<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'last_name', 'email', 'phone', 'identification', 'relationship', 'photo_url', 'pickup_contact', 'role', 'onboarding_status', 'password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, Notifiable;

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'user_uuid', 'uuid');
    }

    public function onboardingDrafts(): HasMany
    {
        return $this->hasMany(OnboardingDraft::class, 'user_uuid', 'uuid');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_uuid', 'uuid');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_uuid', 'uuid');
    }

    public function enrollments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Enrollment::class,
            Participant::class,
            'user_uuid',
            'participant_uuid',
            'uuid',
            'uuid',
        );
    }

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
            'last_login_at' => 'datetime',
        ];
    }
}
