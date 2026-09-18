<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['participant_uuid', 'plan_uuid', 'plan_name', 'session_uuid', 'session_name', 'status', 'plan_type', 'total_amount', 'sibling_discount', 'payment_method', 'starts_at', 'ends_at', 'notes'])]
class Enrollment extends Model
{
    use HasPublicUuid;
    protected function casts(): array
    {
        return ['total_amount' => 'decimal:4', 'sibling_discount' => 'decimal:4', 'payment_method' => 'array', 'starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_uuid', 'uuid');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_uuid', 'uuid');
    }
}
