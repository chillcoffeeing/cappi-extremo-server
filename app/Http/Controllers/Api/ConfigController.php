<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function planPrice(): JsonResponse
    {
        $plan = Plan::operative()->latest('starts_at')->first();

        return response()->json([
            'precio' => $plan ? (float) $plan->price : null,
            'moneda' => $plan?->currency ?? 'USD',
            'descuentoHermano' => [
                'activo' => (bool) ($plan?->sibling_discount_enabled ?? false),
                'montoPorParticipante' => (float) ($plan?->sibling_discount_amount ?? 0),
                'minimoParticipantes' => (int) ($plan?->sibling_discount_min_participants ?? 0),
            ],
        ]);
    }
}