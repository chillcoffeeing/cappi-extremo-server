<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Support\AdminDeleteActions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Solo lectura: las inscripciones nacen del flujo de onboarding / alta
 * post-onboarding (RegisterParticipantInscription), no de un formulario
 * admin en blanco.
 *
 * F-054: "Eliminar" (solo SUPER_ADMIN) usa DeleteEnrollment; la orden de
 * inscripcion no cambia.
 */
class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminDeleteActions::enrollment(redirectToIndex: true),
        ];
    }
}
