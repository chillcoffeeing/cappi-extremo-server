<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nombre' => $this->name,
            'sede' => $this->venue,
            'temporada' => $this->season,
            'estado' => $this->status,
            'portadaUrl' => $this->cover_url,
            'fechaInicio' => $this->starts_at?->format('Y-m-d'),
            'fechaFin' => $this->ends_at?->format('Y-m-d'),
            'fechaTexto' => $this->date_label,
            'duracionTexto' => $this->duration_label,
            'horario' => $this->schedule,
            'cupoTotal' => $this->capacity,
            'cuposDisponible' => $this->available_slots ?? $this->capacity,
            'edadMin' => $this->age_min,
            'edadMax' => $this->age_max,
            'descripcion' => $this->description ?? '',
            // Compatibilidad temporal: `dias` sigue siendo el JSON legado de
            // `plans.days` hasta migrar el portal a la estructura admin
            // (plan_days/plan_day_activities). Ver
            // api/docs/backoffice/02-datos-y-migraciones.md.
            'dias' => $this->relationLoaded('planDays') && $this->planDays->isNotEmpty()
                ? $this->planDays->map(fn ($day): array => [
                    'numeroDia' => $day->day_number,
                    'fecha' => $day->date?->format('Y-m-d'),
                    'titulo' => $day->title,
                    'descripcion' => $day->description ?? '',
                    'ubicacion' => $day->location,
                    'estado' => $day->status,
                    'actividades' => $day->activities->map(fn ($activity): array => [
                        'titulo' => $activity->title,
                        'descripcion' => $activity->description ?? '',
                        'inicio' => $activity->starts_at,
                        'fin' => $activity->ends_at,
                        'ubicacion' => $activity->location,
                        'estado' => $activity->status,
                    ])->values()->all(),
                ])->values()->all()
                : ($this->days ?? []),
            'avance' => [
                'modo' => $this->progress_mode,
                'porcentaje' => $this->progress_percent,
                'etiqueta' => $this->progress_label,
                'diaActual' => $this->currentDay?->day_number,
                'totalDias' => $this->relationLoaded('planDays') ? $this->planDays->count() : $this->planDays()->count(),
            ],
            'avisos' => $this->relationLoaded('announcements')
                ? $this->announcements
                    ->filter(fn ($announcement): bool => $announcement->is_visible
                        && (! $announcement->starts_at || $announcement->starts_at->isPast())
                        && (! $announcement->ends_at || $announcement->ends_at->isFuture()))
                    ->map(fn ($announcement): array => [
                        'titulo' => $announcement->title,
                        'cuerpo' => $announcement->body,
                        'severidad' => $announcement->severity,
                    ])->values()->all()
                : [],
        ];
    }
}
