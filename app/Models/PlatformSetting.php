<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sibling_discount_enabled', 'sibling_discount_min_participants', 'sibling_discount_amount'])]
class PlatformSetting extends Model
{
    use HasPublicUuid;
    protected function casts(): array
    {
        return [
            'sibling_discount_enabled' => 'boolean',
            'sibling_discount_min_participants' => 'integer',
            'sibling_discount_amount' => 'decimal:4',
        ];
    }

    /**
     * Fila única de configuración de plataforma (F-002). Crea el registro con
     * los defaults (mismos que la migración) si aún no existe, de modo que
     * pricing, ConfigController y los tests funcionan sin depender del seeder.
     */
    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            [],
            [
                'sibling_discount_enabled' => true,
                'sibling_discount_min_participants' => 2,
                'sibling_discount_amount' => 20,
            ],
        );
    }
}