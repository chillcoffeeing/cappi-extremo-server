<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\ResolvePendingCoordinatedPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            request()->user()->payments()->latest('paid_at')->latest('id')->get(),
        );
    }

    public function balance(): JsonResponse
    {
        $ordersTotal = request()->user()->orders()->sum('total');
        // F-002: todas las cargas de inscripción viven en órdenes (la
        // consolidada del onboarding + una orden por alta posterior, ambas
        // is_registration=true y ya recalculadas con el descuento hermanos).
        // Así que, si existe al menos una, no se vuelven a sumar los totales de
        // enrolment para evitar doble conteo.
        $hasRegistrationOrder = request()->user()->orders()
            ->where('is_registration', true)
            ->exists();
        $enrollmentsTotal = $hasRegistrationOrder
            ? 0
            : request()->user()->participants()
                ->join('enrollments', 'enrollments.participant_uuid', '=', 'participants.uuid')
                ->sum('enrollments.total_amount');
        $total = $ordersTotal + $enrollmentsTotal;
        $approved = request()->user()->payments()->where('status', 'APROBADO')->sum('amount');

        return response()->json([
            'totalPagar' => (float) $total,
            'totalAbonado' => (float) $approved,
            'saldo' => max((float) $total - (float) $approved, 0),
            'proximaFechaLimite' => null,
            'moneda' => 'USD',
            // F-023: null salvo que el primer pago de la orden de inscripción
            // esté coordinado por WhatsApp y siga pendiente de verificación.
            'primerPagoCoordinado' => app(ResolvePendingCoordinatedPayment::class)->handle(request()->user()),
        ]);
    }

    public function methods(): JsonResponse
    {
        $plan = Plan::operative()->latest('starts_at')->first();

        return response()->json(
            PaymentMethod::where('active', true)->get()->map(
                function (PaymentMethod $method) use ($plan): array {
                    $data = $method->data ?? [];

                    // F-023: si el método es COORDINADO_REMOTO y no tiene un
                    // link propio configurado, se arma con el WhatsApp
                    // operativo del plan activo en vez de duplicar esa
                    // lógica en el portal.
                    if ($method->type === 'COORDINADO_REMOTO') {
                        $whatsapp = $data['whatsapp'] ?? [];
                        if (empty($whatsapp['link'])) {
                            $whatsapp['link'] = $plan?->whatsapp ? "https://wa.me/{$plan->whatsapp}" : null;
                        }
                        $data['whatsapp'] = $whatsapp;
                    }

                    return [
                        'id' => $method->code,
                        'tipo' => $method->type,
                        'nombre' => $method->name,
                        'descripcion' => $method->description,
                        'datos' => $data,
                        'activo' => $method->active,
                    ];
                },
            )->values(),
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hash = hash('sha256', implode('|', [request()->user()->uuid, $data['monto'], $data['fecha'], $data['referencia']]));
        if (Payment::where('idempotency_hash', $hash)->exists()) {
            return response()->json(['message' => 'Este pago ya fue reportado. Evita duplicados.'], 422);
        }

        $method = PaymentMethod::where('code', $data['metodoId'])->where('active', true)->first();
        if (! $method) {
            return response()->json(['message' => 'Método de pago no disponible.'], 422);
        }

        $file = $request->file('comprobante');
        $path = $file->store('comprobantes', 'local');
        $payment = Payment::create([
            'user_uuid' => request()->user()->uuid,
            'paid_at' => $data['fecha'],
            'amount' => $data['monto'],
            'currency' => 'USD',
            'method_code' => $data['metodoId'],
            'method_name' => $method->name,
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

    /**
     * Sirve el comprobante solo al representante dueño del pago. El disco
     * `local` no es accesible por URL publica (ver
     * api/docs/backoffice/01-arquitectura-y-seguridad.md).
     */
    public function receipt(string $payment): StreamedResponse
    {
        $record = request()->user()->payments()
            ->where('uuid', $payment)
            ->whereNotNull('receipt_path')
            ->firstOrFail();

        return Storage::disk('local')->download($record->receipt_path, $record->receipt_name);
    }
}
