<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'venue', 'season', 'status', 'cover_url', 'starts_at', 'ends_at',
    'capacity', 'price', 'currency', 'age_min', 'age_max', 'description',
    'activities', 'staff', 'mini_market', 'whatsapp',
])]
class Plan extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'price' => 'decimal:4',
            'activities' => 'array',
            'staff' => 'array',
            'mini_market' => 'array',
        ];
    }
}
