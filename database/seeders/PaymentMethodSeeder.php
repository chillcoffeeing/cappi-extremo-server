<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Migra los metodos que vivian hardcodeados en PaymentController::METHODS
 * (pre-Fase 4) a la tabla `payment_methods`, que ya existia desde antes
 * pero no tenia modelo ni datos.
 *
 * F-023: `type` deja de ser el catalogo cerrado de proveedores
 * (ZELLE/EFECTIVO/TRANSFERENCIA_BS) y pasa a describir comportamiento
 * (DIRECTO|COORDINADO_REMOTO). Los 3 metodos activos quedan en DIRECTO por
 * ahora -- el admin puede pasar cualquiera a COORDINADO_REMOTO desde
 * /admin/payment-methods sin tocar codigo. Se agrega Binance (catalogo
 * abierto via `code`/`name`, ya no hay limite fijo de proveedores).
 * Transferencia Bs queda desactivada (`active=false`) en vez de eliminarse.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            'met_zelle' => [
                'type' => 'DIRECTO',
                'name' => 'Zelle',
                'description' => 'Transferencia a través de tu banco usando Zelle.',
                'data' => ['instrucciones' => 'Envía el monto desde tu app bancaria con Zelle.', 'detalle' => [['etiqueta' => 'Correo', 'valor' => 'pagos@cappixtremo.com']]],
                'active' => true,
            ],
            'met_binance' => [
                'type' => 'DIRECTO',
                'name' => 'Binance',
                'description' => 'Pago con criptomonedas a través de Binance Pay.',
                'data' => ['instrucciones' => 'Envía el monto equivalente a nuestro Binance Pay ID.', 'detalle' => [['etiqueta' => 'Binance Pay ID', 'valor' => '000000000']]],
                'active' => true,
            ],
            'met_efectivo' => [
                'type' => 'DIRECTO',
                'name' => 'Efectivo',
                'description' => 'Entrega en las oficinas o con un miembro del equipo.',
                'data' => ['instrucciones' => 'Entrega durante horario hábil.', 'detalle' => []],
                'active' => true,
            ],
            'met_transferencia_bs' => [
                'type' => 'DIRECTO',
                'name' => 'Transferencia Bs',
                'description' => 'Transferencia en bolívares.',
                'data' => ['instrucciones' => 'Indica tu cédula como referencia.', 'detalle' => []],
                'active' => false,
            ],
        ];

        foreach ($methods as $code => $attributes) {
            PaymentMethod::updateOrCreate(['code' => $code], $attributes);
        }
    }
}
