<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\IssueAuthTokens;
use App\Actions\Auth\RefreshAuthTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['nombre'],
            'email' => $data['email'],
            'phone' => $data['telefono'],
            'identification' => $data['cedula'],
            'role' => 'USER',
            'onboarding_status' => 'INCOMPLETO',
            'password' => $data['password'],
        ]);

        return response()->json([
            ...(new AuthUserResource($user))->resolve($request),
        ], 201);
    }

    public function login(LoginRequest $request, IssueAuthTokens $issueAuthTokens): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales invalidas'], 422);
        }

        $user->update(['last_login_at' => now()]);
        $tokens = $issueAuthTokens->handle($user, (bool) ($data['remember'] ?? false));

        return response()->json([
            ...$tokens,
            'user' => (new AuthUserResource($user))->resolve($request),
        ]);
    }

    public function refresh(RefreshTokenRequest $request, RefreshAuthTokens $refreshAuthTokens): JsonResponse
    {
        try {
            $session = $refreshAuthTokens->handle($request->string('refreshToken')->toString());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return response()->json([
            ...collect($session)->except('user')->all(),
            'user' => (new AuthUserResource($session['user']))->resolve($request),
        ]);
    }

    public function logout(): JsonResponse
    {
        request()->user()->tokens()->delete();
        RefreshToken::where('user_uuid', request()->user()->uuid)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(null, 204);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        $user = User::where('email', $email)->first();
        if ($user) {
            $token = Str::random(80);
            PortalAuthToken::create(['user_uuid' => $user->uuid, 'email' => $email, 'type' => 'password', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);
            logger()->info('Portal password reset token generated', ['email' => $email, 'token' => $token]);
        }

        return response()->json(null, 204);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8']]);
        $authToken = PortalAuthToken::where('type', 'password')->where('token_hash', hash('sha256', $data['token']))->whereNull('used_at')->first();
        if (! $authToken || $authToken->expires_at->isPast()) {
            return response()->json(['message' => 'El enlace de restablecimiento no es valido o expiro'], 422);
        }
        User::where('uuid', $authToken->user_uuid)->update(['password' => $data['password']]);
        $authToken->update(['used_at' => now()]);

        return response()->json(null, 204);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $token = $request->validate(['token' => ['required', 'string']])['token'];
        $authToken = PortalAuthToken::where('type', 'email')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->first();
        if (! $authToken || $authToken->expires_at->isPast()) {
            return response()->json(['message' => 'El token de verificacion no es valido o expiro'], 422);
        }
        User::where('uuid', $authToken->user_uuid)->update(['email_verified_at' => now()]);
        $authToken->update(['used_at' => now()]);

        return response()->json(null, 204);
    }
}
