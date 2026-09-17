<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Response;

class PlanController extends Controller
{
    public function active(): PlanResource|Response
    {
        $plan = Plan::whereIn('status', ['PUBLICADO', 'EN_CURSO'])
            ->latest('starts_at')
            ->first();

        return $plan ? new PlanResource($plan) : response()->noContent();
    }
}
