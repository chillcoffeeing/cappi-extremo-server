<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'price', 'previous_price', 'variants', 'images', 'description', 'in_stock'])]
class Product extends Model
{
    use HasPublicUuid;
    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'previous_price' => 'decimal:4', 'variants' => 'array', 'images' => 'array', 'in_stock' => 'boolean'];
    }
}
