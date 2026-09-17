<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    private const METHODS = [
        'met_zelle' => ['tipo' => 'ZELLE', 'nombre' => 'Zelle', 'descripcion' => 'Transferencia a través de tu banco usando Zelle.', 'datos' => ['instrucciones' => 'Envía el monto desde tu app bancaria con Zelle.', 'detalle' => [['etiqueta' => 'Correo', 'valor' => 'pagos@cappixtremo.com']]]],
        'met_efectivo' => ['tipo' => 'EFECTIVO', 'nombre' => 'Efectivo', 'descripcion' => 'Entrega en las oficinas o con un miembro del equipo.', 'datos' => ['instrucciones' => 'Entrega durante horario hábil.', 'detalle' => []]],
        'met_transferencia_bs' => ['tipo' => 'TRANSFERENCIA_BS', 'nombre' => 'Transferencia Bs', 'descripcion' => 'Transferencia en bolívares.', 'datos' => ['instrucciones' => 'Indica tu cédula como referencia.', 'detalle' => []]],
    ];

    public function index(): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            request()->user()->payments()->latest('paid_at')->latest('id')->get(),
        );
    }

    public function balance(): JsonResponse
    {
        $ordersTotal = request()->user()->orders()->sum('total');
        // Las inscripciones creadas por onboarding ya tienen su cargo
        // consolidado en una orden; no sumarlas otra vez al balance familiar.
        $hasRegistrationOrder = request()->user()->orders()
            ->where('is_registration', true)
            ->exists();
        $enrollmentsTotal = $hasRegistrationOrder
            ? 0
            : request()->user()->participants()
                ->join('enrollments', 'enrollments.participant_id', '=', 'participants.id')
                ->sum('enrollments.total_amount');
        $total = $ordersTotal + $enrollmentsTotal;
        $approved = request()->user()->payments()->where('status', 'APROBADO')->sum('amount');

        return response()->json([
            'totalPagar' => (float) $total,
            'totalAbonado' => (float) $approved,
            'saldo' => max((float) $total - (float) $approved, 0),
            'proximaFechaLimite' => null,
            'moneda' => 'USD',
        ]);
    }

    public function methods(): JsonResponse
    {
        return response()->json(collect(self::METHODS)->map(
            fn (array $method, string $id): array => ['id' => $id, ...$method, 'activo' => true],
        )->values());
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hash = hash('sha256', implode('|', [request()->user()->id, $data['monto'], $data['fecha'], $data['referencia']]));
        if (Payment::where('idempotency_hash', $hash)->exists()) {
            return response()->json(['message' => 'Este pago ya fue reportado. Evita duplicados.'], 422);
        }

        $method = self::METHODS[$data['metodoId']] ?? null;
        if (! $method) {
            return response()->json(['message' => 'Método de pago no disponible.'], 422);
        }

        $file = $request->file('comprobante');
        $path = $file->store('comprobantes', 'public');
        $payment = Payment::create([
            'user_id' => request()->user()->id,
            'paid_at' => $data['fecha'],
            'amount' => $data['monto'],
            'currency' => 'USD',
            'method_code' => $data['metodoId'],
            'method_name' => $method['nombre'],
            'reference' => $data['referencia'],
            'concept' => 'Reporte de pago',
            'status' => 'PENDIENTE_VERIFICACION',
            'receipt_path' => $path,
            'receipt_name' => $file->getClientOriginalName(),
            'idempotency_hash' => $hash,
        ]);

        return response()->json([
            'data' => (new PaymentResource($payment))->resolve($request),
        ], 201);
    }
}
