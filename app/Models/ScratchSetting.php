<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'value'])]
class ScratchSetting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'decimal:2'];
    }
}