<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_uuid', 'version', 'completed_steps', 'data', 'status'])]
class OnboardingDraft extends Model
{
    use HasPublicUuid;
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'completed_steps' => 'array',
            'data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
