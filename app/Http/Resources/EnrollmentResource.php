<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $active = ! in_array($this->status, ['CANCELADA', 'FINALIZADA'], true);
        $installment = max((float) $this->total_amount - (float) $this->sibling_discount, 0);

        return [
            'id' => 'insc_'.$this->id,
            'participanteId' => 'part_'.$this->participant_id,
            'planId' => $this->plan_id ? 'plan_'.$this->plan_id : '',
            'planNombre' => $this->plan_name,
            'sesionId' => $this->session_id,
            'sesionNombre' => $this->session_name,
            'estado' => $this->status,
            'planTipo' => $this->plan_type,
            'montoTotal' => (float) $this->total_amount,
            'descuentoHermano' => (float) $this->sibling_discount,
            'formaPago' => $this->payment_method ?? ['tipo' => 'CUOTAS', 'depositoInicial' => 0],
            'fechaInicio' => $this->starts_at?->format('Y-m-d'),
            'fechaFin' => $this->ends_at?->format('Y-m-d'),
            'cuotaActual' => $installment,
            'observaciones' => $this->notes,
            'activo' => $active,
            'estadoDominio' => ['activo' => $active, 'estado' => $this->status],
            'resumen' => $this->plan_name.' · '.$this->session_name,
        ];
    }
}
