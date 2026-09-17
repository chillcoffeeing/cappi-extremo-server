<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueAuthTokens
{
    /** @return array{accessToken: string, refreshToken: string, expiresIn: int, remember: bool} */
    public function handle(User $user, bool $remember): array
    {
        return DB::transaction(function () use ($user, $remember): array {
            $accessToken = $user->createToken(
                'portal',
                ['*'],
                now()->addMinutes(15),
            );
            $refreshToken = Str::random(80);
            $refresh = RefreshToken::create([
                'user_id' => $user->id,
                'access_token_id' => $accessToken->accessToken->id,
                'token_hash' => hash('sha256', $refreshToken),
                'remember' => $remember,
                'expires_at' => now()->addDays($remember ? 30 : 1),
            ]);

            return [
                'accessToken' => $accessToken->plainTextToken,
                'refreshToken' => $refreshToken,
                'expiresIn' => 15 * 60 * 1000,
                'remember' => $refresh->remember,
            ];
        });
    }
}
