<?php

namespace App\Http\Resources;

use App\Models\OnboardingDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OnboardingDraft */
class OnboardingDraftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'draftId' => $this->uuid,
            'version' => $this->version,
            'completedSteps' => $this->completed_steps ?? [],
            'data' => $this->data ?? [],
        ];
    }
}
