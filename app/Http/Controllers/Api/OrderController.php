<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkOrderPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = request()->user()->orders()->orderByDesc('is_registration')->latest('ordered_at')->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $items = $request->validated('items');
        $total = collect($items)->sum(fn (array $item): float => $item['qty'] * $item['precio']);
        $order = request()->user()->orders()->create([
            'order_code' => 'ord_'.Str::lower(Str::random(12)),
            'ordered_at' => now()->toDateString(),
            'items' => $items,
            'total' => $total,
            'paid' => 0,
            'status' => 'PENDIENTE_PAGO',
            'is_registration' => false,
        ]);

        return response()->json(['data' => (new OrderResource($order))->resolve($request)], 201);
    }

    public function linkPayment(LinkOrderPaymentRequest $request, string $order): JsonResponse
    {
        $model = request()->user()->orders()->where('order_code', $order)->firstOrFail();
        $data = $request->validated();
        $balance = (float) $model->total - (float) $model->paid;
        if ($balance <= 0) {
            return response()->json(['message' => 'Esta orden ya está pagada; no tiene saldo pendiente.'], 422);
        }
        if ((float) $data['monto'] > $balance) {
            return response()->json(['message' => 'El monto no puede superar el saldo pendiente.'], 422);
        }
        if (! $data['esCompleto'] && (float) $data['monto'] < 20) {
            return response()->json(['message' => 'El abono mínimo es $20.'], 422);
        }

        $file = $request->file('comprobante');
        $path = $file->store('comprobantes', 'public');
        Payment::create([
            'user_id' => request()->user()->id,
            'paid_at' => now()->toDateString(),
            'amount' => $data['monto'],
            'currency' => 'USD',
            'method_code' => $data['metodoId'],
            'method_name' => $data['metodoNombre'],
            'reference' => $data['referencia'],
            'concept' => ($model->is_registration ? 'Inscripción - ' : 'Pedido tienda - ').($model->items[0]['nombre'] ?? 'pedido'),
            'status' => 'PENDIENTE_VERIFICACION',
            'receipt_path' => $path,
            'receipt_name' => $file->getClientOriginalName(),
            'order_id' => $model->order_code,
            'idempotency_hash' => hash('sha256', $model->order_code.'|'.$data['referencia'].'|'.$data['monto']),
        ]);

        return response()->json(['data' => (new OrderResource($model->refresh()))->resolve($request)]);
    }
}
