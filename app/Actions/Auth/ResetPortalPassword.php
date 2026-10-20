<?php

namespace App\Actions\Auth;

use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F-056 (api-A-1): restablece la contraseña del representante con un token
 * de "Olvidé mi contraseña".
 *
 * - Transacción con lock del token: dos envíos del mismo enlace no lo usan dos veces.
 * - La contraseña se guarda por Eloquent (cast `hashed` de User -> bcrypt).
 * - Invalida los demás enlaces de reset pendientes del usuario.
 * - Cierra todas sus sesiones: borra los tokens Sanctum y revoca los refresh tokens.
 */
class ResetPortalPassword
{
    /** @return bool false si el token no existe, ya se usó o expiró. */
    public function handle(string $plainToken, string $password): bool
    {
        return DB::transaction(function () use ($plainToken, $password): bool {
            $authToken = PortalAuthToken::where('type', 'password')
                ->where('token_hash', hash('sha256', $plainToken))
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();
            if (! $authToken || $authToken->expires_at->isPast()) {
                return false;
            }

            $user = User::where('uuid', $authToken->user_uuid)->first();
            if (! $user) {
                return false;
            }

            $user->update(['password' => $password]);

            PortalAuthToken::where('user_uuid', $user->uuid)
                ->where('type', 'password')
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $user->tokens()->delete();
            RefreshToken::where('user_uuid', $user->uuid)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return true;
        });
    }
}
