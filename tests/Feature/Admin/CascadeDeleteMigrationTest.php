<?php

namespace Tests\Feature\Admin;

use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F-053: la migracion 2026_10_19_000000 solo cambia reglas de FK y agrega
 * `representative_snapshot`. No borra ni modifica filas: ni siquiera los
 * huerfanos existentes (user_uuid / participant_uuid NULL).
 *
 * DatabaseMigrations (no RefreshDatabase) a proposito: el test corre down()/up()
 * fuera de transaccion, igual que `php artisan migrate` con
 * `$withinTransaction = false`. En SQLite reconstruir una tabla dentro de una
 * transaccion dispararia acciones ON DELETE en las tablas hijas.
 */
class CascadeDeleteMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const MIGRATION = 'migrations/2026_10_19_000000_cascade_representative_children_and_add_representative_snapshot.php';

    private function migration(): Migration
    {
        return require database_path(self::MIGRATION);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function snapshotAllTables(): array
    {
        $snapshot = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            // `migrations` cambia por definicion (registro de la migracion).
            if (str_starts_with($name, 'sqlite_') || $name === 'migrations') {
                continue;
            }
            $rows = DB::table($name)->get()->map(function (object $row): array {
                $row = (array) $row;
                unset($row['representative_snapshot']);

                return $row;
            })->sortBy(fn (array $row): string => json_encode($row))->values()->all();
            $snapshot[$name] = $rows;
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @return array<string, string> columna => on_delete (SQLite) */
    private function onDeleteRules(string $table): array
    {
        return collect(DB::select("PRAGMA foreign_key_list('{$table}')"))
            ->mapWithKeys(fn (object $fk): array => [$fk->from => strtoupper($fk->on_delete)])
            ->all();
    }

    private function seedData(): void
    {
        $user = User::factory()->create(['photo_url' => 'profiles/x.jpg']);
        $participant = Participant::factory()->create(['user_uuid' => $user->uuid]);
        Enrollment::create([
            'participant_uuid' => $participant->uuid, 'plan_name' => 'Plan', 'session_uuid' => (string) Str::uuid(),
            'session_name' => 'S', 'total_amount' => 100, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
        ]);
        ParticipantCorrectionRequest::create(['participant_uuid' => $participant->uuid, 'section' => 'salud', 'message' => 'x']);
        OnboardingDraft::create(['user_uuid' => $user->uuid, 'status' => 'COMPLETADO']);
        RefreshToken::create(['user_uuid' => $user->uuid, 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()]);
        PortalAuthToken::create(['user_uuid' => $user->uuid, 'email' => $user->email, 'type' => 'RESET', 'token_hash' => hash('sha256', 'b'), 'expires_at' => now()->addDay()]);
        $user->createToken('portal');
        $order = Order::create(['user_uuid' => $user->uuid, 'ordered_at' => '2026-10-01', 'items' => [], 'total' => 100, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => true]);
        Payment::create([
            'user_uuid' => $user->uuid, 'order_uuid' => $order->uuid, 'paid_at' => '2026-10-01', 'amount' => 100, 'currency' => 'USD',
            'method_code' => 'met_zelle', 'method_name' => 'Zelle', 'reference' => 'R', 'status' => 'PENDIENTE_VERIFICACION',
            'receipt_name' => 'r.jpg', 'idempotency_hash' => hash('sha256', 'c'),
        ]);

        // Huerfanos como los que pueden existir hoy en produccion: se dejan como estan.
        $orphanParticipant = Participant::factory()->create(['user_uuid' => null]);
        Enrollment::create([
            'participant_uuid' => null, 'plan_name' => 'Plan', 'session_uuid' => (string) Str::uuid(),
            'session_name' => 'S', 'total_amount' => 100, 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-05',
        ]);
        OnboardingDraft::create(['user_uuid' => null, 'status' => 'INCOMPLETO']);
        Order::create(['user_uuid' => null, 'ordered_at' => '2026-10-01', 'items' => [], 'total' => 5, 'paid' => 0, 'status' => 'PENDIENTE_PAGO', 'is_registration' => false]);
        $this->assertNull($orphanParticipant->fresh()->user_uuid);
    }

    public function test_migration_does_not_delete_or_modify_any_row(): void
    {
        // Volver al estado previo a F-053 con datos (incluidos huerfanos).
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('orders', 'representative_snapshot'));
        $this->seedData();

        $before = $this->snapshotAllTables();
        $this->assertGreaterThan(0, count($before['participants']));

        // up() a traves del Migrator real (respeta $withinTransaction = false).
        DB::table('migrations')->where('migration', basename(self::MIGRATION, '.php'))->delete();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('migrations')->where('migration', basename(self::MIGRATION, '.php'))->count());

        $after = $this->snapshotAllTables();
        $this->assertSame(array_map('count', $before), array_map('count', $after), 'Los conteos de filas cambiaron.');
        $this->assertEquals($before, $after, 'El contenido de alguna fila cambio.');

        $this->assertTrue(Schema::hasColumn('orders', 'representative_snapshot'));
        $this->assertTrue(Schema::hasColumn('payments', 'representative_snapshot'));
        $this->assertSame(0, DB::table('orders')->whereNotNull('representative_snapshot')->count());
        $this->assertSame(0, DB::table('payments')->whereNotNull('representative_snapshot')->count());
    }

    public function test_up_sets_cascade_rules_and_down_restores_null_on_delete(): void
    {
        $cascade = [
            'participants' => 'user_uuid',
            'onboarding_drafts' => 'user_uuid',
            'enrollments' => 'participant_uuid',
            'refresh_tokens' => 'user_uuid',
            'portal_auth_tokens' => 'user_uuid',
        ];
        $unchanged = [
            ['orders', 'user_uuid'], ['payments', 'user_uuid'], ['payments', 'order_uuid'], ['enrollments', 'plan_uuid'],
        ];

        foreach ($cascade as $table => $column) {
            $this->assertSame('CASCADE', $this->onDeleteRules($table)[$column] ?? null, "{$table}.{$column}");
        }
        foreach ($unchanged as [$table, $column]) {
            $this->assertSame('SET NULL', $this->onDeleteRules($table)[$column] ?? null, "{$table}.{$column}");
        }

        $this->migration()->down();

        foreach ($cascade as $table => $column) {
            $this->assertSame('SET NULL', $this->onDeleteRules($table)[$column] ?? null, "{$table}.{$column} tras down()");
        }
        foreach ($unchanged as [$table, $column]) {
            $this->assertSame('SET NULL', $this->onDeleteRules($table)[$column] ?? null, "{$table}.{$column} tras down()");
        }
        $this->assertFalse(Schema::hasColumn('payments', 'representative_snapshot'));

        // up() otra vez por el Migrator real. Queda en un batch propio, asi el
        // rollback de DatabaseMigrations solo revierte esta migracion (las
        // legacy de 2026_09_17 son irreversibles).
        DB::table('migrations')->where('migration', basename(self::MIGRATION, '.php'))->delete();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        foreach ($cascade as $table => $column) {
            $this->assertSame('CASCADE', $this->onDeleteRules($table)[$column] ?? null, "{$table}.{$column} tras up()");
        }
    }
}
