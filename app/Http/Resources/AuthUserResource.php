<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => 'fam_'.$this->id,
            'nombre' => $this->name,
            'email' => $this->email,
            'telefono' => $this->phone ?? '',
            'cedula' => $this->identification ?? '',
            'rol' => $this->role,
            'emailVerified' => $this->email_verified_at !== null,
            'onboardingStatus' => $this->onboarding_status,
            'draftId' => $this->draft_id,
        ];
    }
}
