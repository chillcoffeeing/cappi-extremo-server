<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use Illuminate\Http\Response;

class EnrollmentController extends Controller
{
    public function show(string $participant): EnrollmentResource|Response
    {
        $model = request()->user()->participants()
            ->where('uuid', $participant)
            ->firstOrFail();
        $enrollment = $model->enrollment;

        return $enrollment ? new EnrollmentResource($enrollment) : response()->noContent();
    }
}
