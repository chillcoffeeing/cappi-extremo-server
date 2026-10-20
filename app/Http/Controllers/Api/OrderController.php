<?php

namespace App\Http\Controllers\Api;

use App\Actions\Orders\CreateStoreOrder;
use App\Actions\Payments\LinkOrderPayment;
use App\Exceptions\PaymentActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkOrderPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = request()->user()->orders()->orderByDesc('is_registration')->latest('ordered_at')->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request, CreateStoreOrder $action): JsonResponse
    {
        // F-058: precio, nombre y variante salen del catálogo (Action).
        $order = $action->handle($request->user(), $request->validated('items'));

        return response()->json(['data' => (new OrderResource($order))->resolve($request)], 201);
    }

    public function linkPayment(LinkOrderPaymentRequest $request, string $order, LinkOrderPayment $action): JsonResponse
    {
        // Orden inexistente o ajena -> 404.
        $model = request()->user()->orders()
            ->where('uuid', $order)
            ->firstOrFail();

        try {
            $model = $action->handle($model, $request->validated(), $request->file('comprobante'));
        } catch (PaymentActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => (new OrderResource($model))->resolve($request)]);
    }
}
