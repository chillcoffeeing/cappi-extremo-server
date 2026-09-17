<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use Illuminate\Http\Response;

class EnrollmentController extends Controller
{
    public function show(int $participant): EnrollmentResource|Response
    {
        $enrollment = request()->user()->participants()->findOrFail($participant)->enrollment;

        return $enrollment ? new EnrollmentResource($enrollment) : response()->noContent();
    }
}
