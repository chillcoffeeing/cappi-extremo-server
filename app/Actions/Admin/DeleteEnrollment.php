<?php

namespace App\Actions\Admin;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * F-053 — borrado fisico de UNA inscripcion (solo SUPER_ADMIN,
 * `records.delete`; ver EnrollmentPolicy::delete). Solo la inscripcion: el
 * participante y la orden de inscripcion no cambian.
 */
class DeleteEnrollment
{
    /**
     * @return array{enrollments: int}
     */
    public function handle(Enrollment $enrollment): array
    {
        return DB::transaction(function () use ($enrollment): array {
            $enrollment = Enrollment::whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();
            $enrollment->delete();

            return ['enrollments' => 1];
        });
    }
}
