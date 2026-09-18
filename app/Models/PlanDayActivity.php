<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_day_uuid', 'title', 'description', 'starts_at', 'ends_at',
    'location', 'status', 'sort_order', 'requirements',
])]
class PlanDayActivity extends Model
{
    use HasPublicUuid;

    public function planDay(): BelongsTo
    {
        return $this->belongsTo(PlanDay::class, 'plan_day_uuid', 'uuid');
    }
}
