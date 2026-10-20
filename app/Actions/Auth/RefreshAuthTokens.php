<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefreshAuthTokens
{
    public function __construct(private readonly IssueAuthTokens $issueAuthTokens) {}

    /** @return array{user: User, accessToken: string, refreshToken: string, expiresIn: int, remember: bool} */
    public function handle(string $plainToken): array
    {
        return DB::transaction(function () use ($plainToken): array {
            $refresh = RefreshToken::where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            // F-053 (C-2): un refresh token huerfano (usuario borrado antes de que
            // la FK fuera cascadeOnDelete) responde 401, no 500.
            if (! $refresh || $refresh->revoked_at || $refresh->expires_at->isPast() || ! $refresh->user) {
                throw new RuntimeException('Sesion expirada');
            }

            $refresh->update(['revoked_at' => now()]);
            $tokens = $this->issueAuthTokens->handle($refresh->user, $refresh->remember);

            return ['user' => $refresh->user, ...$tokens];
        });
    }
}
