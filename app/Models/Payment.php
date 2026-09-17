<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'paid_at', 'amount', 'currency', 'method_code', 'method_name', 'reference', 'concept', 'status', 'rejection_reason', 'receipt_path', 'receipt_name', 'order_id', 'idempotency_hash'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'date:Y-m-d', 'amount' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
