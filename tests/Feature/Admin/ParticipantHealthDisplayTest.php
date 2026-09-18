<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Participant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresion: los tabs Salud/Contactos/Seguro/Autorizaciones mostraban el
 * JSON crudo del bloque en vez de los valores formateados.
 */
class ParticipantHealthDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_contacts_and_authorizations_render_formatted_not_as_raw_json(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $user = User::factory()->create();
        $participant = Participant::factory()->for($user, 'user')->create([
            'health' => [
                'tipoSangre' => 'O+', 'alergias' => 'Polen', 'condicionesMedicas' => '',
                'medicamentos' => '', 'discapacidades' => '', 'requiereAcompanante' => false, 'infoAdicional' => '',
            ],
            'emergency_contacts' => [
                ['id' => 'c_001', 'nombre' => 'Maria Perez', 'telefono' => '8095550101', 'parentesco' => 'Madre'],
            ],
            'pickup_contact' => [
                'id' => 'er_001', 'nombre' => 'Jose Perez', 'documento' => '001-7654321-8',
                'telefono' => '8095550103', 'relacion' => 'Padrino', 'esContactoEmergencia' => false,
            ],
            'medical_insurance' => [
                'aseguradora' => 'Seguros Universal', 'poliza' => 'POL-8899',
                'telefonoEmergencias' => '8095550199', 'noTiene' => false,
            ],
            'authorizations' => [
                'autorizaFotos' => true, 'autorizaVideo' => true, 'autorizaActividadesAcuaticas' => false,
                'autorizaTraslados' => true, 'autorizaAtencionMedicaUrgencia' => true,
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get("/admin/participants/{$participant->getKey()}/details")
            ->assertOk();

        // No se compara contra assertDontSee('tipoSangre', ...): Livewire
        // siempre serializa el estado crudo del componente en el atributo
        // oculto wire:snapshot, asi que las claves del array (tipoSangre,
        // aseguradora, etc.) aparecen ahi sin importar que tan bien se
        // formatee la UI visible. Lo que importa es que los VALORES
        // formateados aparezcan como texto visible, no las claves crudas.
        $response->assertSee('O+')
            ->assertSee('Polen')
            ->assertSee('Maria Perez')
            ->assertSee('Madre')
            ->assertSee('Seguros Universal')
            ->assertSee('POL-8899')
            ->assertSee('Jose Perez')
            ->assertSee('Tipo de sangre')
            ->assertSee('Aseguradora')
            ->assertSee('Encargado de retiro');
    }
}
