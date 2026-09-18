<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    // Nota: NO usar WithoutModelEvents. El uuid público de cada modelo
    // (HasPublicUuid) se asigna en el hook `creating`; desactivar los
    // eventos deja `uuid` en NULL para todo lo que crea este seeder.

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(PaymentMethodSeeder::class);

        PlatformSetting::singleton();
        $user = User::updateOrCreate(
            ['email' => 'maria@example.com'],
            [
                'name' => 'Maria',
                'last_name' => 'Perez',
                'phone' => '8095550101',
                'identification' => '001-0000001-1',
                'role' => 'USER',
                'onboarding_status' => 'COMPLETADO',
                'password' => 'password123',
            ],
        );

        $plan = Plan::updateOrCreate(
            ['name' => 'PLAN VACACIONAL - EDICIÓN NAVIDAD'],
            [
                'venue' => 'Finca la Esperanza - Guanare', 'season' => 'Edición Navidad',
                'status' => 'PUBLICADO', 'starts_at' => '2026-12-14', 'ends_at' => '2026-12-18',
                'date_label' => 'Diciembre 14 al 18',
                'duration_label' => '4 días, llegada y salida diaria',
                'schedule' => '8AM - 5 PM',
                // `descripcion` es HTML crudo: el portal lo inyecta tal cual
                // (dangerouslySetInnerHTML) en el hero "en curso". Aquí vive
                // todo lo que antes tenía secciones fijas propias (staff del
                // día, equipo necesario, notas), redactado como parte del día.
                'days' => [
                    ['date' => '2026-12-14', 'numeroDia' => 1, 'descripcion' => <<<'HTML'
                        <p>Bienvenida, acreditación y actividades de integración para romper el hielo entre los grupos.</p>
                        <h4>Staff encargado</h4>
                        <ul>
                            <li>Carlos Mendoza — Coordinador general</li>
                            <li>Ana Torres — Facilitadora de integración</li>
                        </ul>
                        <h4>Equipo necesario</h4>
                        <ul>
                            <li>Traje de baño y toalla</li>
                            <li>Protector solar</li>
                            <li>Ropa cómoda para actividades al aire libre</li>
                        </ul>
                        HTML,
                    ],
                    ['date' => '2026-12-15', 'numeroDia' => 2, 'descripcion' => <<<'HTML'
                        <p>Día de piscina y paseo a caballo. Salida temprano hacia los senderos de la finca.</p>
                        <h4>Staff encargado</h4>
                        <ul>
                            <li>Equipo Cappi Xtremo — Facilitadores</li>
                            <li>Guía externo — Paseos a caballo</li>
                        </ul>
                        <h4>Equipo necesario</h4>
                        <ul>
                            <li>Traje de baño y toalla</li>
                            <li>Zapatos cerrados (para el paseo a caballo)</li>
                            <li>Botella de agua</li>
                        </ul>
                        HTML,
                    ],
                    ['date' => '2026-12-16', 'numeroDia' => 3, 'descripcion' => <<<'HTML'
                        <p>Talleres creativos en la mañana y torneo deportivo en la tarde.</p>
                        <h4>Staff encargado</h4>
                        <ul>
                            <li>Equipo Cappi Xtremo — Facilitadores</li>
                        </ul>
                        <h4>Equipo necesario</h4>
                        <ul>
                            <li>Ropa deportiva</li>
                            <li>Gorra</li>
                        </ul>
                        HTML,
                    ],
                    ['date' => '2026-12-17', 'numeroDia' => 4, 'descripcion' => <<<'HTML'
                        <p>Última jornada: fogata de cierre, entrega de reconocimientos y despedida.</p>
                        <h4>Staff encargado</h4>
                        <ul>
                            <li>Carlos Mendoza — Coordinador general</li>
                        </ul>
                        <h4>Equipo necesario</h4>
                        <ul>
                            <li>Ropa abrigada para la noche</li>
                        </ul>
                        HTML,
                    ],
                ],
                'available_slots' => 100,
                'capacity' => 100, 'price' => 300, 'currency' => 'USD', 'age_min' => 4, 'age_max' => 15,
                'description' => 'Cinco días de plan vacacional decembrino.', 'activities' => ['Piscina', 'Paseos a caballo'],
                'staff' => [['id' => 'st_001', 'nombre' => 'Equipo Cappi Xtremo', 'rol' => 'Facilitadores']],
                'mini_market' => [], 'whatsapp' => '584121234567',
            ],
        );
        $sessionId = (string) Str::uuid();

        $wizardSteps = [
            'datos-basicos' => [
                'nombre' => 'Juan Perez', 'fechaNacimiento' => '2018-03-12', 'genero' => 'MASCULINO',
                'cedula' => '001-1234567-1', 'tallaCamisa' => 'CH', 'pesoKg' => 28,
            ],
            'salud' => [
                'tipoSangre' => 'O+', 'alergias' => 'Polen', 'condicionesMedicas' => '',
                'medicamentos' => '', 'discapacidades' => '', 'requiereAcompanante' => false, 'infoAdicional' => '',
            ],
            'contactos-emergencia' => [
                'contactosEmergencia' => [
                    ['id' => 'c_001', 'nombre' => 'Maria Perez', 'telefono' => '8095550101', 'parentesco' => 'Madre'],
                    ['id' => 'c_002', 'nombre' => 'Carlos Perez', 'telefono' => '8095550102', 'parentesco' => 'Tío'],
                ],
            ],
            'encargado-retiro' => [
                'encargadoRetiro' => [
                    'id' => 'er_001', 'nombre' => 'Jose Perez', 'documento' => '001-7654321-8',
                    'telefono' => '8095550103', 'relacion' => 'Padrino', 'esContactoEmergencia' => false,
                ],
            ],
            'seguro-medico' => [
                'aseguradora' => 'Seguros Universal', 'poliza' => 'POL-8899',
                'telefonoEmergencias' => '8095550199', 'noTiene' => false,
            ],
            'autorizaciones' => [
                'autorizaFotos' => true, 'autorizaVideo' => true, 'autorizaActividadesAcuaticas' => false,
                'autorizaTraslados' => true, 'autorizaAtencionMedicaUrgencia' => true,
            ],
        ];

        $participant = Participant::updateOrCreate(
            ['user_uuid' => $user->uuid, 'name' => 'Juan Perez'],
            [
                'name' => 'Juan Perez', 'birth_date' => '2018-03-12', 'gender' => 'MASCULINO',
                'data_completed' => true,
                'health' => $wizardSteps['salud'],
                'emergency_contacts' => $wizardSteps['contactos-emergencia']['contactosEmergencia'],
                'pickup_contact' => $wizardSteps['encargado-retiro']['encargadoRetiro'],
                'medical_insurance' => $wizardSteps['seguro-medico'],
                'authorizations' => $wizardSteps['autorizaciones'],
                'wizard_steps' => $wizardSteps,
            ],
        );

        Participant::updateOrCreate(
            ['user_uuid' => $user->uuid, 'name' => 'Valentina Perez'],
            [
                'name' => 'Valentina Perez', 'birth_date' => '2015-07-22', 'gender' => 'FEMENINO',
                'data_completed' => false, 'health' => [], 'emergency_contacts' => [],
                'pickup_contact' => null, 'medical_insurance' => [], 'authorizations' => [],
                'wizard_steps' => [],
            ],
        );

        Enrollment::updateOrCreate(
            ['participant_uuid' => $participant->uuid],
            [
                'plan_uuid' => $plan->uuid, 'plan_name' => $plan->name, 'session_uuid' => $sessionId,
                'session_name' => 'Semana 1 - 07/12/2026', 'status' => 'PENDIENTE_PAGO',
                'plan_type' => 'HERMANOS', 'total_amount' => 300, 'sibling_discount' => 20,
                'payment_method' => ['tipo' => 'CUOTAS', 'depositoInicial' => 0],
                'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
            ],
        );

        $valentina = Participant::where('user_uuid', $user->uuid)->where('name', 'Valentina Perez')->first();
        if ($valentina) {
            Enrollment::updateOrCreate(
                ['participant_uuid' => $valentina->uuid],
                [
                    'plan_uuid' => $plan->uuid, 'plan_name' => $plan->name, 'session_uuid' => $sessionId,
                    'session_name' => 'Semana 1 - 07/12/2026', 'status' => 'PENDIENTE_PAGO',
                    'plan_type' => 'HERMANOS', 'total_amount' => 300, 'sibling_discount' => 20,
                    'payment_method' => ['tipo' => 'CUOTAS', 'depositoInicial' => 0],
                    'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
                ],
            );
        }

        $registrationOrder = Order::firstOrNew([
            'user_uuid' => $user->uuid,
            'is_registration' => true,
        ]);
        $registrationOrder->fill([
                'user_uuid' => $user->uuid,
                'ordered_at' => '2026-09-16',
                'items' => [
                    ['nombre' => 'Inscripción · '.$plan->name, 'variante' => 'Semana 1 - 07/12/2026', 'qty' => 2, 'precio' => 300],
                    ['nombre' => 'Descuento hermanos', 'variante' => 'Semana 1 - 07/12/2026', 'qty' => 1, 'precio' => -40],
                ],
                'total' => 560,
                'paid' => 0,
                'status' => 'PENDIENTE_PAGO',
                'is_registration' => true,
            ]);
        $registrationOrder->save();

        foreach ([
            ['name' => 'Gorra Xtremo', 'category' => 'Ropa', 'price' => 15, 'previous_price' => 22, 'variants' => ['Talla única'], 'images' => ['https://picsum.photos/seed/tienda1a/400/400'], 'description' => 'Gorra de perfil con el logo bordado.'],
            ['name' => 'Botella deportiva', 'category' => 'Accesorios', 'price' => 12, 'previous_price' => null, 'variants' => ['Talla única'], 'images' => ['https://picsum.photos/seed/tienda5a/400/400'], 'description' => 'Botella resistente para hidratarse.'],
        ] as $product) {
            Product::updateOrCreate(['name' => $product['name']], [...$product, 'in_stock' => true]);
        }

        $order = Order::firstOrNew([
            'user_uuid' => $user->uuid,
            'is_registration' => false,
        ]);
        $order->fill([
                'user_uuid' => $user->uuid,
                'ordered_at' => '2026-09-16',
                'items' => [['nombre' => 'Gorra Xtremo', 'variante' => 'Talla única', 'qty' => 1, 'precio' => 15]],
                'total' => 15,
                'paid' => 15,
                'status' => 'PAGADA',
                'is_registration' => false,
            ]);
        $order->save();

        $payment = Payment::firstOrNew([
            'user_uuid' => $user->uuid,
            'concept' => 'Pedido tienda - Gorra Xtremo',
        ]);
        $payment->fill([
                'user_uuid' => $user->uuid,
                'paid_at' => '2026-09-16',
                'amount' => 15,
                'currency' => 'USD',
                'method_code' => 'met_zelle',
                'method_name' => 'Zelle',
                'reference' => 'DEMO-001',
                'concept' => 'Pedido tienda - Gorra Xtremo',
                'status' => 'APROBADO',
                'receipt_name' => 'demo-receipt.jpg',
                'order_uuid' => $order->uuid,
                'idempotency_hash' => $payment->idempotency_hash ?? hash('sha256', (string) Str::uuid()),
            ]);
        $payment->save();
    }
}
