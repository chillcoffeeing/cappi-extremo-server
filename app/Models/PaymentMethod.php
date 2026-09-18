<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sin HasPublicUuid: `code` (p.ej. "met_zelle") ya es la clave publica
 * unica que usa el API desde antes de este modelo (PaymentController); no
 * hace falta un segundo identificador.
 */
#[Fillable(['code', 'type', 'name', 'description', 'data', 'active'])]
class PaymentMethod extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
