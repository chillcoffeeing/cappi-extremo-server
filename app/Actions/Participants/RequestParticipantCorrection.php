<?php

namespace App\Actions\Participants;

use App\Models\AdminUser;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;

/**
 * Soporte solicita una correccion de ficha (06-flujos-y-auditoria.md). Solo
 * crea la solicitud; nunca toca los datos del participante ("el
 * representante no debe perder datos al recibir una solicitud").
 */
class RequestParticipantCorrection
{
    public function handle(Participant $participant, string $section, string $message, AdminUser $actor): ParticipantCorrectionRequest
    {
        return $participant->correctionRequests()->create([
            'section' => $section,
            'message' => $message,
            'status' => 'PENDIENTE',
            'requested_by' => $actor->uuid,
        ]);
    }
}
