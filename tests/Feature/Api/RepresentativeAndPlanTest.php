<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepresentativeAndPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_can_read_and_update_account_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'phone' => '8095550101',
            'identification' => '001-0000001-1',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/representante')
            ->assertOk()
            ->assertJsonPath('data.perfil.email', 'maria@example.com');

        $this->putJson('/api/representante/contacto', [
            'nombre' => 'María',
            'apellido' => 'Pérez',
            'correo' => 'maria.updated@example.com',
            'telefono' => '8095550102',
        ])->assertOk()->assertJsonPath('data.perfil.email', 'maria.updated@example.com');
    }

    public function test_representative_can_read_active_plan(): void
    {
        $user = User::factory()->create();
        Plan::create([
            'name' => 'Plan Vacacional',
            'venue' => 'Finca',
            'season' => '2026',
            'status' => 'PUBLICADO',
            'starts_at' => '2026-12-07',
            'ends_at' => '2026-12-11',
            'capacity' => 100,
            'price' => 150,
            'currency' => 'USD',
            'age_min' => 5,
            'age_max' => 15,
            'activities' => ['Piscina'],
            'staff' => [],
            'mini_market' => [],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/planes/activo')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Plan Vacacional')
            ->assertJsonPath('data.precio', 150);
    }

    public function test_representative_photo_is_stored_as_multipart_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->put('/api/representante/foto', [
            'foto' => UploadedFile::fake()->image('profile.jpg'),
        ]);

        $response->assertOk()->assertJsonPath('data.perfil.fotoUrl', fn (string $url): bool => str_contains($url, '/storage/profiles/'));
        $this->assertNotEmpty(Storage::disk('public')->allFiles('profiles'));
    }
}
