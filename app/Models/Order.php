<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'order_code', 'ordered_at', 'items', 'total', 'paid', 'status', 'is_registration'])]
class Order extends Model
{
    protected function casts(): array
    {
        return ['ordered_at' => 'date:Y-m-d', 'items' => 'array', 'total' => 'decimal:4', 'paid' => 'decimal:4', 'is_registration' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
