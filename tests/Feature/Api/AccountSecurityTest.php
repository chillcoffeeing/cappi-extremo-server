<?php

namespace Tests\Feature\Api;

use App\Models\AdminUser;
use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * F-056: seguridad de cuentas del portal (api-A-1, A-8, A-10, B-6, C-1) y
 * compatibilidad con datos/sesiones existentes.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function resetTokenFor(User $user, string $plain, ?\DateTimeInterface $expiresAt = null): PortalAuthToken
    {
        return PortalAuthToken::create([
            'user_uuid' => $user->uuid,
            'email' => $user->email,
            'type' => 'password',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt ?? now()->addHour(),
        ]);
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    // ---- api-A-1 -------------------------------------------------------

    public function test_reset_password_hashes_the_password_and_login_works(): void
    {
        $user = User::factory()->create(['email' => 'maria@example.com', 'password' => 'oldpassword1']);
        $this->resetTokenFor($user, 'plain-reset-token');

        $this->postJson('/api/auth/reset-password', ['token' => 'plain-reset-token', 'password' => 'newpassword1'])
            ->assertNoContent();

        $stored = $user->fresh()->getAuthPassword();
        $this->assertNotSame('newpassword1', $stored);
        $this->assertTrue(Hash::isHashed($stored));
        $this->assertSame('bcrypt', password_get_info($stored)['algoName']);

        $this->login('maria@example.com', 'newpassword1')->assertOk();
        $this->login('maria@example.com', 'oldpassword1')->assertStatus(422);
    }

    public function test_reset_password_closes_every_session_and_other_reset_links(): void
    {
        $user = User::factory()->create(['email' => 'maria@example.com', 'password' => 'oldpassword1']);
        $login = $this->login('maria@example.com', 'oldpassword1')->assertOk();
        $oldRefresh = $login->json('refreshToken');
        $oldAccess = $login->json('accessToken');

        $this->resetTokenFor($user, 'first-link');
        $this->resetTokenFor($user, 'second-link');

        $this->postJson('/api/auth/reset-password', ['token' => 'first-link', 'password' => 'newpassword1'])
            ->assertNoContent();

        // Refresh token previo -> 401.
        $this->postJson('/api/auth/refresh', ['refreshToken' => $oldRefresh])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Sesion expirada']);
        $this->assertSame(0, RefreshToken::where('user_uuid', $user->uuid)->whereNull('revoked_at')->count());

        // Access token Sanctum previo -> 401.
        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($oldAccess)->getJson('/api/representante')->assertUnauthorized();

        // El token usado y el otro enlace pendiente quedan inválidos.
        $this->postJson('/api/auth/reset-password', ['token' => 'first-link', 'password' => 'otherpassword1'])
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->postJson('/api/auth/reset-password', ['token' => 'second-link', 'password' => 'otherpassword1'])
            ->assertStatus(422);

        $this->login('maria@example.com', 'newpassword1')->assertOk();
    }

    public function test_reset_password_rejects_expired_token_without_touching_the_account(): void
    {
        $user = User::factory()->create(['password' => 'oldpassword1']);
        $refresh = RefreshToken::create([
            'user_uuid' => $user->uuid, 'token_hash' => hash('sha256', 'r'), 'remember' => false,
            'expires_at' => now()->addDay(),
        ]);
        $this->resetTokenFor($user, 'expired', now()->subMinute());

        $this->postJson('/api/auth/reset-password', ['token' => 'expired', 'password' => 'newpassword1'])
            ->assertStatus(422)
            ->assertJson(['message' => 'El enlace de restablecimiento no es valido o expiro']);

        $this->assertTrue(Hash::check('oldpassword1', $user->fresh()->getAuthPassword()));
        $this->assertNull($refresh->fresh()->revoked_at);
    }

    public function test_forgot_password_does_not_log_the_token(): void
    {
        User::factory()->create(['email' => 'maria@example.com']);
        Log::spy();

        $this->postJson('/api/auth/forgot-password', ['email' => 'maria@example.com'])->assertNoContent();

        $this->assertSame(1, PortalAuthToken::where('type', 'password')->count());
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []): bool {
            return ! array_key_exists('token', $context) && ! array_key_exists('email', $context);
        })->once();
    }

    // ---- api-A-8 -------------------------------------------------------

    public function test_login_is_throttled_per_ip_and_email_with_spanish_message(): void
    {
        User::factory()->create(['email' => 'maria@example.com', 'password' => 'password123']);
        User::factory()->create(['email' => 'otra@example.com', 'password' => 'password123']);

        for ($i = 0; $i < 5; $i++) {
            $this->login('maria@example.com', 'wrongpass1')->assertStatus(422);
        }
        $this->login('maria@example.com', 'password123')
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.');

        // Otra familia detrás de la misma IP (colegio/CGNAT) no queda bloqueada.
        $this->login('otra@example.com', 'password123')->assertOk();
        // El email se normaliza: mayúsculas no esquivan el límite.
        $this->login('MARIA@example.com', 'password123')->assertStatus(429);
    }

    public function test_forgot_password_register_and_refresh_are_throttled(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'nadie@example.com'])->assertNoContent();
        }
        $this->postJson('/api/auth/forgot-password', ['email' => 'nadie@example.com'])
            ->assertStatus(429)->assertJsonStructure(['message']);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/register', [])->assertStatus(422);
        }
        $this->postJson('/api/auth/register', [])->assertStatus(429)->assertJsonStructure(['message']);

        for ($i = 0; $i < 120; $i++) {
            $this->postJson('/api/auth/refresh', ['refreshToken' => 'nope'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/refresh', ['refreshToken' => 'nope'])->assertStatus(429)->assertJsonStructure(['message']);
    }

    public function test_reset_password_is_throttled(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/reset-password', ['token' => 'guess', 'password' => 'newpassword1'])->assertStatus(422);
        }
        $this->postJson('/api/auth/reset-password', ['token' => 'guess', 'password' => 'newpassword1'])->assertStatus(429);
    }

    public function test_rate_limiters_do_not_500_on_non_string_email_or_token(): void
    {
        // ver_F-056: el limiter corre antes de la validación; un array en
        // `email`/`token` daba 500 ("Array to string conversion").
        $this->postJson('/api/auth/login', ['email' => ['a@b.com'], 'password' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/auth/forgot-password', ['email' => ['x' => 'y']])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/auth/reset-password', ['token' => ['x'], 'password' => 'newpassword1'])
            ->assertStatus(422)->assertJsonValidationErrors(['token']);
        $this->postJson('/api/auth/verify-email', ['token' => ['x']])
            ->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    // ---- api-A-10 / fe-A-12 --------------------------------------------

    public function test_contact_update_with_email_of_another_account_returns_422(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create(['email' => 'maria@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/representante/contacto', [
            'nombre' => 'Maria', 'apellido' => 'Perez', 'correo' => 'ocupado@example.com', 'telefono' => '8095550101',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['correo'])
            ->assertJsonPath('errors.correo.0', 'Ese correo ya está registrado en otra cuenta.');

        // Guardar con su propio correo sigue funcionando.
        $this->putJson('/api/representante/contacto', [
            'nombre' => 'Maria', 'apellido' => 'Perez', 'correo' => 'maria@example.com', 'telefono' => '8095550101',
        ])->assertOk()->assertJsonPath('data.perfil.email', 'maria@example.com');
    }

    public function test_identification_update_with_document_of_another_account_returns_422(): void
    {
        User::factory()->create(['identification' => 'V-11111111']);
        $user = User::factory()->create(['identification' => 'V-22222222']);
        Sanctum::actingAs($user);

        $this->putJson('/api/representante/identificacion', ['documento' => 'V-11111111', 'relacion' => 'Madre'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['documento']);

        $this->putJson('/api/representante/identificacion', ['documento' => 'V-22222222', 'relacion' => 'Madre'])
            ->assertOk();
    }

    // ---- api-B-6 -------------------------------------------------------

    public function test_get_api_user_route_was_removed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user')->assertNotFound();
    }

    // ---- api-C-1 -------------------------------------------------------

    public function test_permission_seeder_does_not_promote_admin_email_and_keeps_existing_roles(): void
    {
        $this->seed(PermissionSeeder::class);

        $devEmail = AdminUser::factory()->create(['email' => 'admin@cappixtremo.com']);
        $devEmail->assignRole('LECTURA');
        $existingSuper = AdminUser::factory()->create(['email' => 'jefa@example.com']);
        $existingSuper->assignRole('SUPER_ADMIN');

        // Re-correr el seeder como en un despliegue.
        $this->seed(PermissionSeeder::class);

        $this->assertSame(['LECTURA'], $devEmail->fresh()->getRoleNames()->all());
        $this->assertSame(['SUPER_ADMIN'], $existingSuper->fresh()->getRoleNames()->all());
        $this->assertTrue($existingSuper->fresh()->hasPermissionTo('records.delete', 'admin'));
    }

    // ---- Compatibilidad (compat-rules 8 y 12) --------------------------

    public function test_existing_session_and_bcrypt_password_keep_working(): void
    {
        // Usuario "de antes del despliegue": password bcrypt ya guardado y un
        // refresh token persistido con el formato actual (sha256 del plano).
        $user = User::factory()->create(['email' => 'vieja@example.com', 'password' => Hash::make('password123')]);
        RefreshToken::create([
            'user_uuid' => $user->uuid, 'token_hash' => hash('sha256', 'persisted-refresh'),
            'remember' => true, 'expires_at' => now()->addDays(20),
        ]);

        $this->postJson('/api/auth/refresh', ['refreshToken' => 'persisted-refresh'])
            ->assertOk()
            ->assertJsonStructure(['accessToken', 'refreshToken', 'expiresIn', 'user']);

        $this->login('vieja@example.com', 'password123')->assertOk();
    }
}
