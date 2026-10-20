<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F-075: /manual sirve el build estatico del manual solo a cuentas admin
 * activas (admin.access), sin salir de la carpeta del build.
 */
class ManualAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/manual-'.uniqid());
        File::ensureDirectoryExists($this->root.'/admin');
        File::ensureDirectoryExists($this->root.'/_astro');
        File::put($this->root.'/index.html', '<h1>Manual</h1>');
        File::put($this->root.'/admin/index.html', '<h1>Admin</h1>');
        File::put($this->root.'/_astro/app.abc123.css', 'body{}');
        File::put($this->root.'/secreto.php', '<?php echo 1;');
        File::put(storage_path('framework/testing/fuera.html'), 'fuera');
        config(['app.manual_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        File::delete(storage_path('framework/testing/fuera.html'));

        parent::tearDown();
    }

    private function actingAsAdmin(string $role, bool $active = true): AdminUser
    {
        $this->seed(PermissionSeeder::class);
        $admin = AdminUser::factory()->create(['is_active' => $active]);
        $admin->assignRole($role);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_guest_is_sent_to_the_panel_login(): void
    {
        $this->get('/manual/admin/')->assertRedirect('/admin/login');
        $this->assertStringEndsWith('/manual/admin', (string) session('url.intended'));
    }

    public function test_any_admin_role_reads_the_manual(): void
    {
        $this->actingAsAdmin('LECTURA');

        $index = $this->get('/manual')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(realpath($this->root.'/index.html'), $index->baseResponse->getFile()->getRealPath());
        $this->assertStringStartsWith('text/html', (string) $index->headers->get('Content-Type'));

        $admin = $this->get('/manual/admin/')->assertOk();
        $this->assertSame(realpath($this->root.'/admin/index.html'), $admin->baseResponse->getFile()->getRealPath());

        $css = $this->get('/manual/_astro/app.abc123.css')->assertOk();
        $this->assertStringStartsWith('text/css', (string) $css->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $css->headers->get('Cache-Control'));
    }

    public function test_inactive_admin_and_portal_user_are_rejected(): void
    {
        $this->actingAsAdmin('SUPER_ADMIN', active: false);
        $this->get('/manual/')->assertForbidden();
    }

    public function test_portal_session_is_not_an_admin_session(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $this->get('/manual/')->assertRedirect('/admin/login');
    }

    public function test_no_path_traversal_and_only_known_file_types(): void
    {
        $this->actingAsAdmin('SUPER_ADMIN');

        $this->get('/manual/../fuera.html')->assertNotFound();
        $this->get('/manual/..%2Ffuera.html')->assertNotFound();
        $this->get('/manual/secreto.php')->assertNotFound();
        $this->get('/manual/no-existe/')->assertNotFound();
    }

    public function test_missing_build_is_a_clear_404(): void
    {
        $this->actingAsAdmin('SUPER_ADMIN');
        config(['app.manual_path' => storage_path('framework/testing/no-hay-manual')]);

        $this->get('/manual/')->assertNotFound();
    }
}
