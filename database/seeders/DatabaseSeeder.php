<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Nota: NO usar WithoutModelEvents. El uuid público de cada modelo
    // (HasPublicUuid) se asigna en el hook `creating`; desactivar los
    // eventos deja `uuid` en NULL para todo lo que crea este seeder.

    /**
     * Seed de producción (F-020): solo infraestructura administrativa +
     * un plan real de ejemplo. Sin usuario/participantes/órdenes/pagos/
     * productos demo -- esos existían para desarrollo local, no deben
     * llegar a una base de datos en vivo.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(PaymentMethodSeeder::class);

        Plan::updateOrCreate(
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
                'sibling_discount_enabled' => true,
                'sibling_discount_min_participants' => 2,
                'sibling_discount_amount' => 20,
            ],
        );
    }
}
