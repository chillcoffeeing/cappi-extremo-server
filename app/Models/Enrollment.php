<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['participant_id', 'plan_id', 'plan_name', 'session_id', 'session_name', 'status', 'plan_type', 'total_amount', 'sibling_discount', 'payment_method', 'starts_at', 'ends_at', 'notes'])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return ['total_amount' => 'decimal:4', 'sibling_discount' => 'decimal:4', 'payment_method' => 'array', 'starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
