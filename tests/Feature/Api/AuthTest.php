<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_portal_user_shape(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nombre' => 'Maria Perez',
            'email' => 'maria@example.com',
            'telefono' => '8095550101',
            'cedula' => '001-0000001-1',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('nombre', 'Maria Perez')
            ->assertJsonPath('emailVerified', false)
            ->assertJsonPath('onboardingStatus', 'INCOMPLETO')
            ->assertJsonPath('rol', 'USER');
    }

    public function test_login_returns_sanctum_access_token(): void
    {
        $user = User::factory()->create([
            'email' => 'maria@example.com',
            'password' => 'password123',
            'phone' => '8095550101',
            'identification' => '001-0000001-1',
            'draft_id' => 'draft_test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => true,
        ])->assertOk()
            ->assertJsonPath('user.email', 'maria@example.com')
            ->assertJsonPath('expiresIn', 900000)
            ->assertJsonStructure(['accessToken', 'refreshToken', 'user']);
    }

    public function test_logout_revokes_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_refresh_rotates_the_token_and_rejects_replay(): void
    {
        $user = User::factory()->create([
            'email' => 'maria@example.com',
            'password' => 'password123',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'remember' => false,
        ])->assertOk();

        $oldRefresh = $login->json('refreshToken');
        $refresh = $this->postJson('/api/auth/refresh', [
            'refreshToken' => $oldRefresh,
        ])->assertOk();

        $refresh->assertJsonStructure(['accessToken', 'refreshToken', 'user']);
        $this->postJson('/api/auth/refresh', ['refreshToken' => $oldRefresh])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Sesion expirada']);
    }
}
