<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'maria@example.com'],
            [
                'name' => 'Maria',
                'last_name' => 'Perez',
                'phone' => '8095550101',
                'identification' => '001-0000001-1',
                'role' => 'USER',
                'onboarding_status' => 'COMPLETADO',
                'draft_id' => 'draft_demo',
                'password' => 'password123',
            ],
        );

        $plan = Plan::updateOrCreate(
            ['name' => 'Plan Vacacional Decembrino'],
            [
                'venue' => 'Finca la Esperanza - Guanare', 'season' => 'Decembrino 2026',
                'status' => 'PUBLICADO', 'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
                'capacity' => 100, 'price' => 150, 'currency' => 'USD', 'age_min' => 5, 'age_max' => 15,
                'description' => 'Cinco días de plan vacacional decembrino.', 'activities' => ['Piscina', 'Paseos a caballo'],
                'staff' => [['id' => 'st_001', 'nombre' => 'Equipo Cappi Xtremo', 'rol' => 'Facilitadores']],
                'mini_market' => [], 'whatsapp' => '584121234567',
            ],
        );

        $participant = Participant::updateOrCreate(
            ['user_id' => $user->id, 'slug' => 'juan-perez'],
            [
                'name' => 'Juan Perez', 'birth_date' => '2018-03-12', 'gender' => 'MASCULINO',
                'data_completed' => false, 'health' => [], 'emergency_contacts' => [],
                'pickup_contact' => null, 'medical_insurance' => [], 'authorizations' => [],
            ],
        );

        Enrollment::updateOrCreate(
            ['participant_id' => $participant->id],
            [
                'plan_id' => $plan->id, 'plan_name' => $plan->name, 'session_id' => 'sesion_001',
                'session_name' => 'Semana 1 - 07/12/2026', 'status' => 'PENDIENTE_PAGO',
                'plan_type' => 'INDIVIDUAL', 'total_amount' => 150, 'sibling_discount' => 0,
                'payment_method' => ['tipo' => 'CUOTAS', 'depositoInicial' => 0],
                'starts_at' => '2026-12-07', 'ends_at' => '2026-12-11',
            ],
        );

        foreach ([
            ['name' => 'Gorra Xtremo', 'category' => 'Ropa', 'price' => 15, 'previous_price' => 22, 'variants' => ['Talla única'], 'images' => ['https://picsum.photos/seed/tienda1a/400/400'], 'description' => 'Gorra de perfil con el logo bordado.'],
            ['name' => 'Botella deportiva', 'category' => 'Accesorios', 'price' => 12, 'previous_price' => null, 'variants' => ['Talla única'], 'images' => ['https://picsum.photos/seed/tienda5a/400/400'], 'description' => 'Botella resistente para hidratarse.'],
        ] as $product) {
            Product::updateOrCreate(['name' => $product['name']], [...$product, 'in_stock' => true]);
        }

        $order = Order::updateOrCreate(
            ['order_code' => 'ord_demo_001'],
            [
                'user_id' => $user->id,
                'ordered_at' => '2026-09-16',
                'items' => [['nombre' => 'Gorra Xtremo', 'variante' => 'Talla única', 'qty' => 1, 'precio' => 15]],
                'total' => 15,
                'paid' => 15,
                'status' => 'PAGADA',
                'is_registration' => false,
            ],
        );

        Payment::updateOrCreate(
            ['idempotency_hash' => hash('sha256', 'demo-payment-001')],
            [
                'user_id' => $user->id,
                'paid_at' => '2026-09-16',
                'amount' => 15,
                'currency' => 'USD',
                'method_code' => 'met_zelle',
                'method_name' => 'Zelle',
                'reference' => 'DEMO-001',
                'concept' => 'Pedido tienda - Gorra Xtremo',
                'status' => 'APROBADO',
                'receipt_name' => 'demo-receipt.jpg',
                'order_id' => $order->order_code,
            ],
        );
    }
}
