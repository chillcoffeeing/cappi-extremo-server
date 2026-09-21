<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_uuid', 'name',
    'birth_date',
    'gender',
    'identification',
    'photo_url',
    'shirt_size',
    'weight_kg',
    'data_completed',
    'health',
    'emergency_contacts',
    'pickup_contact',
    'medical_insurance',
    'wizard_steps',
    'reviewed_at',
    'reviewed_by',
])]
#[Visible([
    'id',
    'name',
    'birth_date',
    'gender',
    'identification',
    'photo_url',
    'data_completed',
    'wizard_steps',
    'health',
    'emergency_contacts',
    'pickup_contact',
    'medical_insurance',
])]
class Participant extends Model
{
    use HasFactory, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'data_completed' => 'boolean',
            'weight_kg' => 'decimal:2',
            'health' => 'array',
            'emergency_contacts' => 'array',
            'pickup_contact' => 'array',
            'medical_insurance' => 'array',
            'wizard_steps' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'participant_uuid', 'uuid');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(ParticipantCorrectionRequest::class, 'participant_uuid', 'uuid');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by', 'uuid');
    }
}
