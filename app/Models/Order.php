<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['user_uuid', 'ordered_at', 'items', 'total', 'paid', 'status', 'is_registration', 'cancellation_reason', 'cancelled_by', 'cancelled_at'])]
class Order extends Model
{
    use HasPublicUuid, LogsActivity;

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date:Y-m-d',
            'items' => 'array',
            'total' => 'decimal:4',
            'paid' => 'decimal:4',
            'is_registration' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_uuid', 'uuid');
    }

    /** Saldo contable: total - abonado (solo pagos APROBADOS suman a `paid`). */
    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid, 4);
    }

    /** Suma de los pagos de esta orden que siguen PENDIENTE_VERIFICACION. */
    public function pendingVerificationAmount(): float
    {
        return round((float) $this->payments()->where('status', 'PENDIENTE_VERIFICACION')->sum('amount'), 4);
    }

    /**
     * F-047: saldo reportable = saldo - pagos en revisión. Es el máximo que el
     * representante puede reportar ahora sin que la suma de reportes supere
     * el saldo (hallazgo H-1 de F-046). Nunca negativo.
     */
    public function reportableBalance(): float
    {
        return max(0.0, round($this->balance() - $this->pendingVerificationAmount(), 4));
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'cancelled_by', 'uuid');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paid'])
            ->logOnlyDirty()
            ->useLogName('order');
    }
}
