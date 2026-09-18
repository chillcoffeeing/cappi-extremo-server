<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Filament\Resources\Enrollments\EnrollmentResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Solo lectura: las inscripciones nacen del flujo de onboarding / alta
 * post-onboarding (RegisterParticipantInscription), no de un formulario
 * admin en blanco.
 */
class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;
}
