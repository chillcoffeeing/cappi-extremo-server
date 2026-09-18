<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'name', 'venue', 'season', 'status', 'paused_from_status', 'status_reason', 'cover_url', 'starts_at', 'ends_at',
    'capacity', 'price', 'currency', 'age_min', 'age_max', 'description',
    'activities', 'staff', 'mini_market', 'whatsapp', 'date_label',
    'duration_label', 'schedule', 'days', 'available_slots',
    'progress_mode', 'progress_percent', 'current_day_uuid', 'progress_label',
    'progress_note', 'progress_updated_by', 'progress_updated_at',
    'sibling_discount_enabled', 'sibling_discount_min_participants', 'sibling_discount_amount',
])]
class Plan extends Model
{
    use HasPublicUuid, LogsActivity;

    /**
     * Estados operativos: solo uno puede ocupar PUBLICADO/EN_CURSO/PAUSADO a
     * la vez (ver api/docs/backoffice/02-datos-y-migraciones.md). 'ACTIVO'
     * era el valor libre previo a Fase 2 (backoffice); PUBLICADO es su
     * equivalente en la maquina de estados nueva.
     */
    public const OPERATIVE_STATUSES = ['PUBLICADO', 'EN_CURSO'];

    public const LOCKED_STATUSES = ['PUBLICADO', 'EN_CURSO', 'PAUSADO'];

    /**
     * Mismos defaults que la columna en la migracion (F-017). Necesarios
     * aqui ademas de en la migracion: `Model::create()` no relee la fila de
     * la base tras el INSERT, asi que un create() que omite estos 3 campos
     * (p. ej. fixtures de test) quedaria con el atributo en memoria en null
     * pese a que la fila en la base sí tiene el default de columna aplicado.
     */
    protected $attributes = [
        'sibling_discount_enabled' => true,
        'sibling_discount_min_participants' => 2,
        'sibling_discount_amount' => 20,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'price' => 'decimal:4',
            'activities' => 'array',
            'staff' => 'array',
            'mini_market' => 'array',
            'days' => 'array',
            'progress_percent' => 'integer',
            'progress_updated_at' => 'datetime',
            'sibling_discount_enabled' => 'boolean',
            'sibling_discount_min_participants' => 'integer',
            'sibling_discount_amount' => 'decimal:4',
        ];
    }

    /**
     * El plan visible/operativo para el API publico (portal, pricing,
     * onboarding). Reemplaza al antiguo `where('status', 'ACTIVO')`.
     */
    public function scopeOperative(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPERATIVE_STATUSES);
    }

    public function planDays(): HasMany
    {
        return $this->hasMany(PlanDay::class, 'plan_uuid', 'uuid');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(PlanAnnouncement::class, 'plan_uuid', 'uuid');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'plan_uuid', 'uuid');
    }

    public function currentDay(): BelongsTo
    {
        return $this->belongsTo(PlanDay::class, 'current_day_uuid', 'uuid');
    }

    public function progressUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'progress_updated_by', 'uuid');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'status_reason', 'price', 'capacity', 'available_slots', 'progress_mode', 'progress_percent'])
            ->logOnlyDirty()
            ->useLogName('plan');
    }
}
