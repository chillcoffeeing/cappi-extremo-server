<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_representantes_appears_before_participantes_in_the_sidebar(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $html = $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->getContent();

        $representantesPos = strpos($html, 'Representantes');
        $participantesPos = strpos($html, 'Participantes');

        $this->assertNotFalse($representantesPos);
        $this->assertNotFalse($participantesPos);
        $this->assertLessThan($participantesPos, $representantesPos);
    }
}
