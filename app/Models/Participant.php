<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'name',
    'slug',
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
    'authorizations',
    'wizard_steps',
])]
#[Visible([
    'id',
    'name',
    'slug',
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
    'authorizations',
])]
class Participant extends Model
{
    use HasFactory;

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
            'authorizations' => 'array',
            'wizard_steps' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }
}
