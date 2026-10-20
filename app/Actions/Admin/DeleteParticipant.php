<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\DeletesPublicPhotosAfterCommit;
use App\Actions\Inscriptions\RecalculateInscriptionOrders;
use App\Models\Enrollment;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F-053 — borrado fisico de UN participante (solo SUPER_ADMIN,
 * `records.delete`; ver ParticipantPolicy::delete).
 *
 * Borra su inscripcion, sus solicitudes de correccion y el participante; la
 * foto (disco public) se borra despues del commit. Luego re-precia las
 * ordenes de inscripcion del representante con RecalculateInscriptionOrders
 * (el descuento hermanos depende del tamano de la familia). Las ordenes no
 * cambian de cantidad: la orden que lo cubria sigue cubriendo las mismas
 * plazas; solo se recalcula el descuento.
 */
class DeleteParticipant
{
    use DeletesPublicPhotosAfterCommit;

    public function __construct(private readonly RecalculateInscriptionOrders $recalculateInscriptionOrders) {}

    /**
     * @return array{enrollments: int, correctionRequests: int, photos: int}
     */
    public function handle(Participant $participant): array
    {
        return DB::transaction(function () use ($participant): array {
            $participant = Participant::whereKey($participant->getKey())->lockForUpdate()->firstOrFail();
            $user = $participant->user_uuid
                ? User::where('uuid', $participant->user_uuid)->lockForUpdate()->first()
                : null;

            $deleted = [
                'enrollments' => Enrollment::where('participant_uuid', $participant->uuid)->delete(),
                'correctionRequests' => ParticipantCorrectionRequest::where('participant_uuid', $participant->uuid)->delete(),
                'photos' => count($this->deletePublicPhotosAfterCommit([$participant->photo_url])),
            ];

            $participant->delete();

            if ($user) {
                $this->recalculateInscriptionOrders->handle($user);
            }

            return $deleted;
        });
    }
}
