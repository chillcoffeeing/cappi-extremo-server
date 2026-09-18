<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'plan_uuid', 'day_number', 'date', 'title', 'description', 'location',
    'status', 'cover_url', 'sort_order', 'is_visible',
])]
class PlanDay extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_visible' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_uuid', 'uuid');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlanDayActivity::class, 'plan_day_uuid', 'uuid')->orderBy('sort_order');
    }
}
