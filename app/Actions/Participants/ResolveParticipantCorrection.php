<?php

namespace App\Actions\Participants;

use App\Exceptions\ParticipantActionException;
use App\Models\AdminUser;
use App\Models\ParticipantCorrectionRequest;
use Illuminate\Support\Facades\DB;

class ResolveParticipantCorrection
{
    public function handle(ParticipantCorrectionRequest $request, string $resolution, AdminUser $actor, string $status = 'RESUELTA'): ParticipantCorrectionRequest
    {
        return DB::transaction(function () use ($request, $resolution, $actor, $status): ParticipantCorrectionRequest {
            $request = ParticipantCorrectionRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== 'PENDIENTE') {
                throw new ParticipantActionException('Esta solicitud ya fue resuelta.');
            }

            $request->update([
                'status' => $status,
                'resolution' => $resolution,
                'resolved_by' => $actor->uuid,
                'resolved_at' => now(),
            ]);

            return $request->fresh();
        });
    }
}
