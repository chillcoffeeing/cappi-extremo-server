<?php

namespace App\Http\Resources;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Participant */
class ParticipantWizardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'participantId' => $this->id,
            'datosCompletos' => $this->data_completed,
            'completedSteps' => array_keys($this->wizard_steps ?? []),
            'data' => $this->wizard_steps ?? [],
        ];
    }
}
