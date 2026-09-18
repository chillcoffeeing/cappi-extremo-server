<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Migra los 3 metodos que vivian hardcodeados en
 * PaymentController::METHODS (pre-Fase 4) a la tabla `payment_methods`, que
 * ya existia desde antes pero no tenia modelo ni datos.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            'met_zelle' => [
                'type' => 'ZELLE',
                'name' => 'Zelle',
                'description' => 'Transferencia a través de tu banco usando Zelle.',
                'data' => ['instrucciones' => 'Envía el monto desde tu app bancaria con Zelle.', 'detalle' => [['etiqueta' => 'Correo', 'valor' => 'pagos@cappixtremo.com']]],
            ],
            'met_efectivo' => [
                'type' => 'EFECTIVO',
                'name' => 'Efectivo',
                'description' => 'Entrega en las oficinas o con un miembro del equipo.',
                'data' => ['instrucciones' => 'Entrega durante horario hábil.', 'detalle' => []],
            ],
            'met_transferencia_bs' => [
                'type' => 'TRANSFERENCIA_BS',
                'name' => 'Transferencia Bs',
                'description' => 'Transferencia en bolívares.',
                'data' => ['instrucciones' => 'Indica tu cédula como referencia.', 'detalle' => []],
            ],
        ];

        foreach ($methods as $code => $attributes) {
            PaymentMethod::updateOrCreate(['code' => $code], [...$attributes, 'active' => true]);
        }
    }
}
