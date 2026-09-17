<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'draft_id', 'version', 'completed_steps', 'data', 'status'])]
class OnboardingDraft extends Model
{
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
        return $this->belongsTo(User::class);
    }
}
