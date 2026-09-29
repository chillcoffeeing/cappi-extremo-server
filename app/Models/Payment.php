<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['user_uuid', 'order_uuid', 'paid_at', 'amount', 'currency', 'method_code', 'method_name', 'reference', 'concept', 'status', 'rejection_reason', 'receipt_path', 'receipt_name', 'idempotency_hash', 'reviewed_by', 'reviewed_at'])]
class Payment extends Model
{
    use HasPublicUuid, LogsActivity;

    protected function casts(): array
    {
        return ['paid_at' => 'date:Y-m-d', 'amount' => 'decimal:4', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_uuid', 'uuid');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by', 'uuid');
    }

    /**
     * F-052 (A-1): un pago RECHAZADO no cuenta como duplicado. Solo los
     * pagos pendientes o aprobados bloquean un nuevo reporte con el mismo
     * hash de idempotencia.
     */
    public static function isDuplicateReport(string $hash): bool
    {
        return static::where('idempotency_hash', $hash)
            ->where('status', '!=', 'RECHAZADO')
            ->exists();
    }

    /**
     * F-052 (A-1): libera el hash de los pagos RECHAZADOS que todavia lo
     * ocupan (datos anteriores a que RejectPayment lo reescribiera), para que
     * el nuevo reporte no choque con el indice unico `idempotency_hash`.
     */
    public static function releaseRejectedHash(string $hash): void
    {
        static::where('idempotency_hash', $hash)
            ->where('status', 'RECHAZADO')
            ->get()
            ->each(fn (Payment $payment) => $payment->update([
                'idempotency_hash' => $payment->releasedIdempotencyHash(),
            ]));
    }

    /** Hash unico que reemplaza al original cuando el pago se rechaza. */
    public function releasedIdempotencyHash(): string
    {
        return hash('sha256', 'rejected|'.$this->uuid.'|'.$this->idempotency_hash);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'rejection_reason'])
            ->logOnlyDirty()
            ->useLogName('payment');
    }
}
