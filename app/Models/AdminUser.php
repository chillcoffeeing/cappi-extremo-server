<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AdminUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class AdminUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory, HasPublicUuid, HasRoles, Notifiable;

    /**
     * Fase 1: gate real por permiso. `is_active` se revisa aparte, en
     * `EnsureAdminIsActive`, para poder cerrar la sesion en curso (no solo
     * bloquear el siguiente login).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->can('admin.access');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
